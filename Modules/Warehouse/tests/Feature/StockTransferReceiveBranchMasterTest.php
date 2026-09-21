<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class StockTransferReceiveBranchMasterTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();

        DB::table('company_user_branches')->delete();
        DB::table('company_users')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
            $this->activeSchemaName = null;
        }

        $this->dropLeftoverSchemas();

        parent::tearDown();
    }

    /**
     * Cross-branch receive must create a branch-scoped product and variant
     * master in the receiving branch so the product appears in its product
     * list, and stock must land under that branch variant.
     */
    public function test_receive_creates_branch_product_master_in_receiving_branch(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] =
            $this->createCompanyWithMemberAndBranches();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $hqBranchId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
            $productCode,
        ] = $this->seedWarehouseAndVariants($hqBranchId, $branchBId);

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 20,
        ]);

        DB::table('stock_layers')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 20,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferId = DB::table('stock_transfers')->insertGetId([
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $branchBWarehouseId,
            'status' => 'draft',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferItemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variant1Id,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $this->actingAs($user)->post(
            route('warehouse.stock-transfers.ship', $transferId)
        );

        $this->actingAs($user)->post(
            route('warehouse.stock-transfers.receive', $transferId),
            [
                'received_items' => [
                    [
                        'stock_transfer_item_id' => $transferItemId,
                        'qty_received' => 5,
                    ],
                ],
            ]
        );

        tenancy()->initialize($tenantId);

        /*
         * Branch B must now have its own product + variant master
         * mirroring the source code/sku.
         */
        $branchProduct = DB::table('products')
            ->where('branch_id', $branchBId)
            ->where('code', $productCode)
            ->first();

        $this->assertNotNull(
            $branchProduct,
            'Receiving branch has no mirrored product master.'
        );

        $sourceVariant = DB::table('product_variants')
            ->where('id', $variant1Id)
            ->first();

        $branchVariant = DB::table('product_variants')
            ->where('branch_id', $branchBId)
            ->where('sku', $sourceVariant->sku)
            ->first();

        $this->assertNotNull(
            $branchVariant,
            'Receiving branch has no mirrored variant master.'
        );

        $this->assertSame(
            $branchProduct->id,
            (int) $branchVariant->product_id,
            'Mirrored variant must belong to the mirrored product.'
        );

        /*
         * Stock in the destination warehouse must be recorded under the
         * branch B variant, not the HQ variant.
         */
        $destStock = DB::table('stock_balances')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $branchVariant->id)
            ->first();

        $this->assertNotNull($destStock);
        $this->assertSame(5, (int) $destStock->qty_on_hand);

        $hqStockUnderBranchVariant = DB::table('stock_balances')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNull($hqStockUnderBranchVariant);

        /*
         * The mirrored product must appear in Branch B's product list.
         */
        tenancy()->end();

        $branchUser = $this->createBranchUser($tenantId, $branchBId);

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchBId,
        ]);

        $response = $this->actingAs($branchUser)
            ->get(route('product.hub', ['tab' => 'items']));

        $response->assertInertia(
            fn ($page) => $page
                ->component('Product/Index')
                ->has('products.data.0', fn ($product) => $product
                    ->where('code', $productCode)
                    ->where('total_stock', 5)
                    ->etc())
        );

        tenancy()->end();
    }

    /**
     * When the receiving branch already has a product/variant master with
     * the same code/sku, receive must reuse it (no duplicate) and simply
     * add the quantity.
     */
    public function test_receive_reuses_existing_branch_master_and_adds_qty(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] =
            $this->createCompanyWithMemberAndBranches();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $hqBranchId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
            $productCode,
        ] = $this->seedWarehouseAndVariants($hqBranchId, $branchBId);

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 20,
        ]);

        DB::table('stock_layers')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 20,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Branch B already created its own master for the same product.
         */
        $sourceVariant = DB::table('product_variants')
            ->where('id', $variant1Id)
            ->first();
        $sourceProduct = DB::table('products')
            ->where('id', $sourceVariant->product_id)
            ->first();

        $existingVariantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $branchBId,
            'product_id' => DB::table('products')->insertGetId([
                'branch_id' => $branchBId,
                'code' => $sourceProduct->code,
                'name' => 'Branch B Own Widget',
                'category_id' => $sourceProduct->category_id,
                'uom_id' => $sourceProduct->uom_id,
                'product_type' => 'single',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'sku' => $sourceVariant->sku,
            'variant_name' => 'Branch B Own Variant',
            // Identitas varian = (sku, warna): samakan warna agar teruji reuse.
            'attributes' => $sourceVariant->attributes,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_balances')->insert([
            'product_variant_id' => $existingVariantId,
            'warehouse_id' => $branchBWarehouseId,
            'qty_on_hand' => 2,
        ]);

        $transferId = DB::table('stock_transfers')->insertGetId([
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $branchBWarehouseId,
            'status' => 'draft',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferItemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variant1Id,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $this->actingAs($user)->post(
            route('warehouse.stock-transfers.ship', $transferId)
        );

        $this->actingAs($user)->post(
            route('warehouse.stock-transfers.receive', $transferId),
            [
                'received_items' => [
                    [
                        'stock_transfer_item_id' => $transferItemId,
                        'qty_received' => 5,
                    ],
                ],
            ]
        );

        tenancy()->initialize($tenantId);

        /*
         * No duplicate master rows created for Branch B.
         */
        $branchBProductCount = DB::table('products')
            ->where('branch_id', $branchBId)
            ->where('code', $productCode)
            ->count();

        $this->assertSame(1, $branchBProductCount);

        $sku = DB::table('product_variants')->where('id', $variant1Id)->value('sku');

        $branchBVariantCount = DB::table('product_variants')
            ->where('branch_id', $branchBId)
            ->where('sku', $sku)
            ->count();

        $this->assertSame(1, $branchBVariantCount);

        /*
         * Quantity added to the existing branch master variant: 2 + 5 = 7.
         */
        $destStock = DB::table('stock_balances')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $existingVariantId)
            ->first();

        $this->assertNotNull($destStock);
        $this->assertSame(7, (int) $destStock->qty_on_hand);

        tenancy()->end();
    }

    /**
     * Same-branch transfer (warehouse to warehouse within one branch) must
     * not duplicate the product master; stock stays under the same variant.
     */
    public function test_same_branch_receive_keeps_single_master(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] =
            $this->createCompanyWithMemberAndBranches();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $hqBranchId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
            $productCode,
        ] = $this->seedWarehouseAndVariants($hqBranchId, $branchBId);

        $sameBranchWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-HQ-SECOND-'.uniqid(),
            'name' => 'HQ Second Warehouse',
            'warehouse_type' => 'retail',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 20,
        ]);

        DB::table('stock_layers')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 20,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferId = DB::table('stock_transfers')->insertGetId([
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $sameBranchWarehouseId,
            'status' => 'draft',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferItemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variant1Id,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $this->actingAs($user)->post(
            route('warehouse.stock-transfers.ship', $transferId)
        );

        $this->actingAs($user)->post(
            route('warehouse.stock-transfers.receive', $transferId),
            [
                'received_items' => [
                    [
                        'stock_transfer_item_id' => $transferItemId,
                        'qty_received' => 5,
                    ],
                ],
            ]
        );

        tenancy()->initialize($tenantId);

        $productCount = DB::table('products')
            ->where('code', $productCode)
            ->count();

        $this->assertSame(1, $productCount);

        $destStock = DB::table('stock_balances')
            ->where('warehouse_id', $sameBranchWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNotNull($destStock);
        $this->assertSame(5, (int) $destStock->qty_on_hand);

        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->createCompanyWithBranches();

        $id = uniqid('bmt_owner_');

        $user = User::factory()->create([
            'email' => 'owner_'.$id.'@acme.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'role' => 'owner',
            'is_default' => true,
        ]);

        DB::table('company_user_branches')->insert([
            [
                'company_user_id' => $companyUser->id,
                'branch_id' => $hqBranchId,
            ],
            [
                'company_user_id' => $companyUser->id,
                'branch_id' => $branchBId,
            ],
        ]);

        return [$tenantId, $hqBranchId, $branchBId, $user];
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function createCompanyWithBranches(): array
    {
        $id = uniqid('bmt_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Branch Master Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ_BMT_'.$id,
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_BMT_'.$id,
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        return [$tenant->id, (int) $hqBranchId, (int) $branchBId];
    }

    private function createBranchUser(string $tenantId, int $branchBId): User
    {
        $id = uniqid('bmt_branch_');

        $user = User::factory()->create([
            'email' => 'branch_'.$id.'@acme.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'role' => 'owner',
            'branch_id' => $branchBId,
            'is_default' => true,
        ]);

        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $branchBId,
        ]);

        return $user;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: string}
     */
    private function seedWarehouseAndVariants(
        int $hqBranchId,
        int $branchBId
    ): array {
        $suffix = uniqid();

        $hqWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-HQ-BMT-'.$suffix,
            'name' => 'HQ Central Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-BRB-BMT-'.$suffix,
            'name' => 'Branch B Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Category '.$suffix,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS '.$suffix,
            'code' => 'PCS'.$suffix,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'PRD-BMT-'.$suffix,
            'name' => 'Widget '.$suffix,
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $hqBranchId,
            'product_id' => $productId,
            'sku' => 'SKU-BMT-'.$suffix,
            'variant_name' => 'Widget Variant '.$suffix,
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            (int) $hqWarehouseId,
            (int) $branchBWarehouseId,
            (int) $variantId,
            'PRD-BMT-'.$suffix,
        ];
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            DB::statement(
                'DROP SCHEMA IF EXISTS "'.
                $schemaName.
                '" CASCADE'
            );
        } catch (\Exception $e) {
            // Ignore cleanup failures.
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "
                SELECT schema_name
                FROM information_schema.schemata
                WHERE schema_name NOT IN (
                    'public',
                    'information_schema'
                )
                AND schema_name NOT LIKE 'pg_%'
                "
            );

            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;

                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Exception $e) {
            // Ignore cleanup failures.
        }
    }
}
