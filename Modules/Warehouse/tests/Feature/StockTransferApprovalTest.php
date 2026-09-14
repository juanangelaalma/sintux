<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Approval\Application\CreateApprovalRule;
use Modules\Approval\Models\ApprovalMapping;
use Modules\Approval\Models\ApprovalTransactionType;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class StockTransferApprovalTest extends TestCase
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
     * Dengan rule aktif, transfer non-HO di atas threshold
     * membuat mapping approval dan tetap pending.
     */
    public function test_transfer_with_rule_creates_mapping_and_pending(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $hoUser] =
            $this->createCompanyWithMemberAndBranches();

        $branchUser = $this->createBranchUser($tenantId, $branchBId);

        tenancy()->initialize($tenantId);
        [$hqWarehouseId, $branchBWarehouseId, $variant1Id] =
            $this->seedWarehouseAndVariants($hqBranchId, $branchBId);
        $this->createStockTransferRule($hoUser->id, 5);
        tenancy()->end();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchBId,
        ]);

        $response = $this->actingAs($branchUser)->post(
            route('warehouse.stock-transfers.store'),
            [
                'from_warehouse_id' => $branchBWarehouseId,
                'to_warehouse_id' => $hqWarehouseId,
                'items' => [
                    ['product_variant_id' => $variant1Id, 'qty' => 10],
                ],
            ]
        );

        $response->assertRedirect(route('warehouse.stock-transfers.index'));

        tenancy()->initialize($tenantId);

        $transfer = DB::table('stock_transfers')->orderByDesc('id')->first();

        $this->assertNotNull($transfer);
        $this->assertSame('pending_approval', $transfer->status);

        $mapping = ApprovalMapping::where('transaction_type', 'stock_transfer')
            ->where('transaction_id', $transfer->id)
            ->first();

        $this->assertNotNull($mapping);
        $this->assertSame('pending', $mapping->overall_status->value);
        $this->assertEquals(10, (float) $mapping->total);
        $this->assertSame('QTY', $mapping->currency_code);
    }

    /**
     * Endpoint lama ditolak bila ada mapping; approval via inbox
     * mengubah status menjadi draft (siap ship).
     */
    public function test_old_approve_blocked_then_inbox_approve_sets_draft(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $hoUser] =
            $this->createCompanyWithMemberAndBranches();

        $branchUser = $this->createBranchUser($tenantId, $branchBId);

        tenancy()->initialize($tenantId);
        [$hqWarehouseId, $branchBWarehouseId, $variant1Id] =
            $this->seedWarehouseAndVariants($hqBranchId, $branchBId);
        $this->createStockTransferRule($hoUser->id, 5);

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $branchBWarehouseId,
            'qty_on_hand' => 20,
        ]);
        tenancy()->end();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchBId,
        ]);

        $this->actingAs($branchUser)->post(
            route('warehouse.stock-transfers.store'),
            [
                'from_warehouse_id' => $branchBWarehouseId,
                'to_warehouse_id' => $hqWarehouseId,
                'items' => [
                    ['product_variant_id' => $variant1Id, 'qty' => 8],
                ],
            ]
        );

        tenancy()->initialize($tenantId);
        $transferId = DB::table('stock_transfers')->orderByDesc('id')->value('id');
        $mappingId = ApprovalMapping::where('transaction_type', 'stock_transfer')
            ->where('transaction_id', $transferId)
            ->value('id');
        tenancy()->end();

        $this->assertNotNull($mappingId);

        // Endpoint lama harus menolak dan mengarahkan ke inbox.
        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $hqBranchId,
        ]);

        $blocked = $this->actingAs($hoUser)
            ->from(route('warehouse.stock-transfers.show', $transferId))
            ->post(route('warehouse.stock-transfers.approve', $transferId), [
                'decision' => 'approve',
            ]);

        $blocked->assertSessionHasErrors('approval');

        // Approval via inbox oleh approver sesuai rule.
        $inbox = $this->actingAs($hoUser)
            ->from(route('warehouse.stock-transfers.show', $transferId))
            ->post(route('approval.mappings.approve', $mappingId), [
                'comment' => 'Setuju via inbox',
            ]);

        $inbox->assertSessionHasNoErrors();

        tenancy()->initialize($tenantId);

        $this->assertSame(
            'draft',
            DB::table('stock_transfers')->where('id', $transferId)->value('status')
        );
        $this->assertSame(
            'approved',
            ApprovalMapping::where('id', $mappingId)->first()->overall_status->value
        );
    }

    /**
     * Qty di bawah threshold rule -> langsung draft tanpa mapping.
     */
    public function test_below_threshold_goes_draft_directly(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $hoUser] =
            $this->createCompanyWithMemberAndBranches();

        $branchUser = $this->createBranchUser($tenantId, $branchBId);

        tenancy()->initialize($tenantId);
        [$hqWarehouseId, $branchBWarehouseId, $variant1Id] =
            $this->seedWarehouseAndVariants($hqBranchId, $branchBId);
        $this->createStockTransferRule($hoUser->id, 100);

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $branchBWarehouseId,
            'qty_on_hand' => 20,
        ]);
        tenancy()->end();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchBId,
        ]);

        $response = $this->actingAs($branchUser)->post(
            route('warehouse.stock-transfers.store'),
            [
                'from_warehouse_id' => $branchBWarehouseId,
                'to_warehouse_id' => $hqWarehouseId,
                'items' => [
                    ['product_variant_id' => $variant1Id, 'qty' => 2],
                ],
            ]
        );

        $response->assertRedirect(route('warehouse.stock-transfers.index'));

        tenancy()->initialize($tenantId);

        $transfer = DB::table('stock_transfers')->orderByDesc('id')->first();

        $this->assertNotNull($transfer);
        $this->assertSame('draft', $transfer->status);
        $this->assertSame(
            0,
            ApprovalMapping::where('transaction_type', 'stock_transfer')
                ->where('transaction_id', $transfer->id)
                ->count()
        );
    }

    /**
     * Pindah stok dalam satu branch (regular -> retail milik sendiri)
     * langsung draft tanpa approval, meski dari non-HO dan tanpa rule.
     */
    public function test_same_branch_transfer_goes_draft_directly(): void
    {
        [$tenantId, $hqBranchId, $branchBId] =
            $this->createCompanyWithBranches();

        $branchUser = $this->createBranchUser($tenantId, $branchBId);

        tenancy()->initialize($tenantId);
        [$regularId, $retailId, $variant1Id] =
            $this->seedSameBranchWarehouses($branchBId);

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $regularId,
            'qty_on_hand' => 500,
        ]);
        tenancy()->end();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchBId,
        ]);

        $response = $this->actingAs($branchUser)->post(
            route('warehouse.stock-transfers.store'),
            [
                'from_warehouse_id' => $regularId,
                'to_warehouse_id' => $retailId,
                'items' => [
                    ['product_variant_id' => $variant1Id, 'qty' => 250],
                ],
            ]
        );

        $response->assertRedirect(route('warehouse.stock-transfers.index'));

        tenancy()->initialize($tenantId);

        $transfer = DB::table('stock_transfers')->orderByDesc('id')->first();

        $this->assertNotNull($transfer);
        $this->assertSame('draft', $transfer->status);
        $this->assertSame(
            0,
            ApprovalMapping::where('transaction_type', 'stock_transfer')
                ->where('transaction_id', $transfer->id)
                ->count()
        );
    }

    /**
     * Transfer satu branch tetap draft walau ada rule aktif
     * dengan threshold di bawah qty (rule hanya untuk antar branch).
     */
    public function test_same_branch_transfer_ignores_active_rule(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $hoUser] =
            $this->createCompanyWithMemberAndBranches();

        $branchUser = $this->createBranchUser($tenantId, $branchBId);

        tenancy()->initialize($tenantId);
        [$regularId, $retailId, $variant1Id] =
            $this->seedSameBranchWarehouses($branchBId);
        $this->createStockTransferRule($hoUser->id, 5);

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $regularId,
            'qty_on_hand' => 500,
        ]);
        tenancy()->end();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchBId,
        ]);

        $response = $this->actingAs($branchUser)->post(
            route('warehouse.stock-transfers.store'),
            [
                'from_warehouse_id' => $regularId,
                'to_warehouse_id' => $retailId,
                'items' => [
                    ['product_variant_id' => $variant1Id, 'qty' => 100],
                ],
            ]
        );

        $response->assertRedirect(route('warehouse.stock-transfers.index'));

        tenancy()->initialize($tenantId);

        $transfer = DB::table('stock_transfers')->orderByDesc('id')->first();

        $this->assertNotNull($transfer);
        $this->assertSame('draft', $transfer->status);
        $this->assertSame(
            0,
            ApprovalMapping::where('transaction_type', 'stock_transfer')
                ->where('transaction_id', $transfer->id)
                ->count()
        );
    }

    private function createStockTransferRule(int $approverId, float $minQty): void
    {
        $type = ApprovalTransactionType::where('key', 'stock_transfer')->firstOrFail();

        app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $type->id,
            'name' => 'Transfer Stok > '.$minQty.' QTY',
            'min_amount' => $minQty,
            'currency_code' => 'QTY',
            'scope_all_users' => true,
            'apply_to_existing_draft' => true,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$approverId]],
            ],
        ], $approverId);
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        [$tenantId, $hqBranchId, $branchBId] = $this->createCompanyWithBranches();

        $id = uniqid('st_owner_');

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
        $id = uniqid('st_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Stock Transfer Approval Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
        ]);

        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ_ST_'.$id,
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_ST_'.$id,
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
        $id = uniqid('st_branch_');

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
            'code' => 'WH-HQ-ST-'.$suffix,
            'name' => 'HQ Central Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-BRB-ST-'.$suffix,
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

    /**
     * Dua gudang (regular + retail) dalam satu branch yang sama,
     * plus satu varian produk untuk item transfer.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function seedSameBranchWarehouses(int $branchId): array
    {
        $suffix = uniqid();

        $regularId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'WH-REG-SB-'.$suffix,
            'name' => 'Regular Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $retailId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'WH-RIT-SB-'.$suffix,
            'name' => 'Retail Warehouse',
            'warehouse_type' => 'retail',
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
            'branch_id' => $branchId,
            'code' => 'PRD-'.$suffix,
            'name' => 'Widget '.$suffix,
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'SKU-'.$suffix,
            'variant_name' => 'Widget Variant '.$suffix,
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            (int) $regularId,
            (int) $retailId,
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
