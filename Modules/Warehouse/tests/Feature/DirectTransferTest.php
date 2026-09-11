<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class DirectTransferTest extends TestCase
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
     * HO dapat membuat transfer langsung ke branch non-HO tanpa stock request.
     * Status awal langsung draft (siap ship).
     */
    public function test_ho_can_create_direct_transfer_as_draft(): void
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
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 20,
        ]);

        tenancy()->end();

        $response = $this->actingAs($user)->post(
            route('warehouse.stock-transfers.store'),
            [
                'from_warehouse_id' => $hqWarehouseId,
                'to_warehouse_id' => $branchBWarehouseId,
                'items' => [
                    ['product_variant_id' => $variant1Id, 'qty' => 5],
                ],
            ]
        );

        $response->assertRedirect(route('warehouse.stock-transfers.index'));

        tenancy()->initialize($tenantId);

        $transfer = DB::table('stock_transfers')->orderByDesc('id')->first();

        $this->assertNotNull($transfer);
        $this->assertSame($hqWarehouseId, (int) $transfer->from_warehouse_id);
        $this->assertSame($branchBWarehouseId, (int) $transfer->to_warehouse_id);
        $this->assertSame('draft', $transfer->status);
        $this->assertNull($transfer->stock_request_id);
        $this->assertSame($user->id, (int) $transfer->created_by);

        $item = DB::table('stock_transfer_items')
            ->where('stock_transfer_id', $transfer->id)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame($variant1Id, (int) $item->product_variant_id);
        $this->assertEquals(5, (float) $item->qty);
    }

    /**
     * Branch non-HO yang membuat transfer ke branch lain
     * harus menunggu approval HO (status pending_approval).
     */
    public function test_branch_user_creates_transfer_as_pending_approval(): void
    {
        [$tenantId, $hqBranchId, $branchBId] =
            $this->createCompanyWithBranches();

        $branchUser = $this->createBranchUser($tenantId, $branchBId);

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchBId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        tenancy()->end();

        $response = $this->actingAs($branchUser)->post(
            route('warehouse.stock-transfers.store'),
            [
                'from_warehouse_id' => $branchBWarehouseId,
                'to_warehouse_id' => $hqWarehouseId,
                'items' => [
                    ['product_variant_id' => $variant1Id, 'qty' => 3],
                ],
            ]
        );

        $response->assertRedirect(route('warehouse.stock-transfers.index'));

        tenancy()->initialize($tenantId);

        $transfer = DB::table('stock_transfers')->orderByDesc('id')->first();

        $this->assertNotNull($transfer);
        $this->assertSame($branchBWarehouseId, (int) $transfer->from_warehouse_id);
        $this->assertSame($hqWarehouseId, (int) $transfer->to_warehouse_id);
        $this->assertSame('pending_approval', $transfer->status);
        $this->assertNull($transfer->stock_request_id);
        $this->assertSame($branchUser->id, (int) $transfer->created_by);
    }

    public function test_store_rejects_same_source_and_destination_warehouse(): void
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
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        tenancy()->end();

        $response = $this->actingAs($user)
            ->from(route('warehouse.stock-transfers.index'))
            ->post(
                route('warehouse.stock-transfers.store'),
                [
                    'from_warehouse_id' => $hqWarehouseId,
                    'to_warehouse_id' => $hqWarehouseId,
                    'items' => [
                        ['product_variant_id' => $variant1Id, 'qty' => 5],
                    ],
                ]
            );

        $response->assertSessionHasErrors('from_warehouse_id');

        tenancy()->initialize($tenantId);

        $this->assertSame(0, DB::table('stock_transfers')->count());
    }

    public function test_store_rejects_source_warehouse_outside_accessible_branches(): void
    {
        [$tenantId, $hqBranchId, $branchBId] =
            $this->createCompanyWithBranches();

        $branchUser = $this->createBranchUser($tenantId, $branchBId);

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchBId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        tenancy()->end();

        $response = $this->actingAs($branchUser)
            ->from(route('warehouse.stock-transfers.index'))
            ->post(
                route('warehouse.stock-transfers.store'),
                [
                    'from_warehouse_id' => $hqWarehouseId,
                    'to_warehouse_id' => $branchBWarehouseId,
                    'items' => [
                        ['product_variant_id' => $variant1Id, 'qty' => 5],
                    ],
                ]
            );

        $response->assertSessionHasErrors('from_warehouse_id');

        tenancy()->initialize($tenantId);

        $this->assertSame(0, DB::table('stock_transfers')->count());
    }

    /**
     * Transfer langsung HO (auto-draft) wajib lolos cek stok saat create.
     */
    public function test_store_rejects_draft_when_source_stock_insufficient(): void
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
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 2,
        ]);

        tenancy()->end();

        $response = $this->actingAs($user)
            ->from(route('warehouse.stock-transfers.index'))
            ->post(
                route('warehouse.stock-transfers.store'),
                [
                    'from_warehouse_id' => $hqWarehouseId,
                    'to_warehouse_id' => $branchBWarehouseId,
                    'items' => [
                        ['product_variant_id' => $variant1Id, 'qty' => 5],
                    ],
                ]
            );

        $response->assertSessionHasErrors('items');

        tenancy()->initialize($tenantId);

        $this->assertSame(0, DB::table('stock_transfers')->count());
    }

    /**
     * Transfer pending (non-HO) tidak dicek stok saat create;
     * cek dilakukan saat HO approve karena stok bisa berubah selama menunggu.
     */
    public function test_pending_transfer_skips_stock_check_at_create(): void
    {
        [$tenantId, $hqBranchId, $branchBId] =
            $this->createCompanyWithBranches();

        $branchUser = $this->createBranchUser($tenantId, $branchBId);

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchBId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        tenancy()->end();

        $response = $this->actingAs($branchUser)->post(
            route('warehouse.stock-transfers.store'),
            [
                'from_warehouse_id' => $branchBWarehouseId,
                'to_warehouse_id' => $hqWarehouseId,
                'items' => [
                    ['product_variant_id' => $variant1Id, 'qty' => 50],
                ],
            ]
        );

        $response->assertRedirect(route('warehouse.stock-transfers.index'));

        tenancy()->initialize($tenantId);

        $transfer = DB::table('stock_transfers')->orderByDesc('id')->first();

        $this->assertNotNull($transfer);
        $this->assertSame($branchBWarehouseId, (int) $transfer->from_warehouse_id);
        $this->assertSame('pending_approval', $transfer->status);
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->createCompanyWithBranches();

        $id = uniqid('dt_owner_');

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
        $id = uniqid('dt_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Direct Transfer Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
        ]);

        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ_DT_'.$id,
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_DT_'.$id,
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
        $id = uniqid('dt_branch_');

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
     * @return array{0: int, 1: int, 2: int}
     */
    private function seedWarehouseAndVariants(
        int $hqBranchId,
        int $branchBId
    ): array {
        $suffix = uniqid();

        $hqWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-HQ-DT-'.$suffix,
            'name' => 'HQ Central Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-BRB-DT-'.$suffix,
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
            'code' => 'PRD-'.$suffix,
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
            'sku' => 'SKU-'.$suffix,
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
