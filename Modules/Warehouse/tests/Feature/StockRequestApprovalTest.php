<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class StockRequestApprovalTest extends TestCase
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

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
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

    public function test_hq_can_fully_approve_request(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        tenancy()->initialize($tenantId);
        [$hqWarehouseId, $branchBWarehouseId, $variant1Id, $variant2Id] = $this->seedWarehouseAndVariants($hqBranchId, $branchBId);

        // Seed stock at HQ (20 units for variant1, 20 units for variant2)
        DB::table('stock_balances')->insert([
            ['product_variant_id' => $variant1Id, 'warehouse_id' => $hqWarehouseId, 'qty_on_hand' => 20],
            ['product_variant_id' => $variant2Id, 'warehouse_id' => $hqWarehouseId, 'qty_on_hand' => 20],
        ]);

        $requestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'pending',
            'note' => 'Permintaan stok reguler',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_request_items')->insert([
            ['stock_request_id' => $requestId, 'product_variant_id' => $variant1Id, 'qty_requested' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['stock_request_id' => $requestId, 'product_variant_id' => $variant2Id, 'qty_requested' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.approve', $requestId), [
            'items' => [
                ['product_variant_id' => $variant1Id, 'qty_approved' => 10],
                ['product_variant_id' => $variant2Id, 'qty_approved' => 5],
            ],
        ]);

        $response->assertRedirect(route('warehouse.stock-requests.show', $requestId));

        tenancy()->initialize($tenantId);
        $requestRow = DB::table('stock_requests')->where('id', $requestId)->first();
        $this->assertSame('approved', $requestRow->status);

        $transferRow = DB::table('stock_transfers')->where('stock_request_id', $requestId)->first();
        $this->assertNotNull($transferRow);
        $this->assertSame('draft', $transferRow->status);
        $this->assertEquals($hqWarehouseId, $transferRow->from_warehouse_id);
        $this->assertEquals($branchBWarehouseId, $transferRow->to_warehouse_id);

        $transferItems = DB::table('stock_transfer_items')->where('stock_transfer_id', $transferRow->id)->get();
        $this->assertCount(2, $transferItems);
        tenancy()->end();
    }

    public function test_hq_can_partially_approve_request(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        tenancy()->initialize($tenantId);
        [$hqWarehouseId, $branchBWarehouseId, $variant1Id, $variant2Id] = $this->seedWarehouseAndVariants($hqBranchId, $branchBId);

        DB::table('stock_balances')->insert([
            ['product_variant_id' => $variant1Id, 'warehouse_id' => $hqWarehouseId, 'qty_on_hand' => 20],
            ['product_variant_id' => $variant2Id, 'warehouse_id' => $hqWarehouseId, 'qty_on_hand' => 20],
        ]);

        $requestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'pending',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_request_items')->insert([
            ['stock_request_id' => $requestId, 'product_variant_id' => $variant1Id, 'qty_requested' => 10, 'created_at' => now(), 'updated_at' => now()],
            ['stock_request_id' => $requestId, 'product_variant_id' => $variant2Id, 'qty_requested' => 5, 'created_at' => now(), 'updated_at' => now()],
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.approve', $requestId), [
            'items' => [
                ['product_variant_id' => $variant1Id, 'qty_approved' => 5], // partial
                ['product_variant_id' => $variant2Id, 'qty_approved' => 0], // 0
            ],
        ]);

        $response->assertRedirect(route('warehouse.stock-requests.show', $requestId));

        tenancy()->initialize($tenantId);
        $requestRow = DB::table('stock_requests')->where('id', $requestId)->first();
        $this->assertSame('partially_approved', $requestRow->status);

        $transferRow = DB::table('stock_transfers')->where('stock_request_id', $requestId)->first();
        $this->assertNotNull($transferRow);

        $transferItems = DB::table('stock_transfer_items')->where('stock_transfer_id', $transferRow->id)->get();
        $this->assertCount(1, $transferItems);
        $this->assertEquals($variant1Id, $transferItems->first()->product_variant_id);
        $this->assertEquals(5, $transferItems->first()->qty);
        tenancy()->end();
    }

    public function test_hq_can_reject_request(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        tenancy()->initialize($tenantId);
        [$hqWarehouseId, $branchBWarehouseId, $variant1Id, $variant2Id] = $this->seedWarehouseAndVariants($hqBranchId, $branchBId);

        DB::table('stock_balances')->insert([
            ['product_variant_id' => $variant1Id, 'warehouse_id' => $hqWarehouseId, 'qty_on_hand' => 20],
        ]);

        $requestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'pending',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_request_items')->insert([
            ['stock_request_id' => $requestId, 'product_variant_id' => $variant1Id, 'qty_requested' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.approve', $requestId), [
            'items' => [
                ['product_variant_id' => $variant1Id, 'qty_approved' => 0],
            ],
        ]);

        $response->assertRedirect(route('warehouse.stock-requests.show', $requestId));

        tenancy()->initialize($tenantId);
        $requestRow = DB::table('stock_requests')->where('id', $requestId)->first();
        $this->assertSame('rejected', $requestRow->status);

        $transferCount = DB::table('stock_transfers')->where('stock_request_id', $requestId)->count();
        $this->assertSame(0, $transferCount);
        tenancy()->end();
    }

    public function test_approval_rejected_when_qty_exceeds_available_stock(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        tenancy()->initialize($tenantId);
        [$hqWarehouseId, $branchBWarehouseId, $variant1Id] = $this->seedWarehouseAndVariants($hqBranchId, $branchBId);

        // HQ stock is only 3
        DB::table('stock_balances')->insert([
            ['product_variant_id' => $variant1Id, 'warehouse_id' => $hqWarehouseId, 'qty_on_hand' => 3],
        ]);

        $requestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'pending',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_request_items')->insert([
            ['stock_request_id' => $requestId, 'product_variant_id' => $variant1Id, 'qty_requested' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.approve', $requestId), [
            'items' => [
                ['product_variant_id' => $variant1Id, 'qty_approved' => 5], // 5 > available 3
            ],
        ]);

        $response->assertSessionHasErrors(['items']);
    }

    public function test_cannot_approve_already_processed_request(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        tenancy()->initialize($tenantId);
        [$hqWarehouseId, $branchBWarehouseId, $variant1Id] = $this->seedWarehouseAndVariants($hqBranchId, $branchBId);

        $requestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved', // already processed
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_request_items')->insert([
            ['stock_request_id' => $requestId, 'product_variant_id' => $variant1Id, 'qty_requested' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.approve', $requestId), [
            'items' => [
                ['product_variant_id' => $variant1Id, 'qty_approved' => 10],
            ],
        ]);

        $response->assertSessionHasErrors(['stock_request']);
    }

    public function test_user_without_hq_branch_access_cannot_approve(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();

        // Create user with ONLY Branch B access (no HQ branch access)
        $branchOnlyUser = User::factory()->create(['email' => 'branch_only_' . uniqid() . '@acme.test', 'role' => 'user']);
        $companyUser = CompanyUser::create([
            'user_id' => $branchOnlyUser->id,
            'tenant_id' => $tenantId,
            'role' => 'member',
            'is_default' => true,
        ]);
        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $branchBId,
        ]);

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        tenancy()->initialize($tenantId);
        [$hqWarehouseId, $branchBWarehouseId, $variant1Id] = $this->seedWarehouseAndVariants($hqBranchId, $branchBId);

        $requestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'pending',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_request_items')->insert([
            ['stock_request_id' => $requestId, 'product_variant_id' => $variant1Id, 'qty_requested' => 10, 'created_at' => now(), 'updated_at' => now()],
        ]);
        tenancy()->end();

        $response = $this->actingAs($branchOnlyUser)->post(route('warehouse.stock-requests.approve', $requestId), [
            'items' => [
                ['product_variant_id' => $variant1Id, 'qty_approved' => 10],
            ],
        ]);

        $response->assertStatus(403);
    }

    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('stra_');
        $schemaName = 'sch_' . $id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Approval Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        tenancy()->initialize($tenant);
        $existingHq = DB::table('branches')->where('code', 'HQ')->value('id');
        $hqBranchId = $existingHq ? (int) $existingHq : DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $codeBrb = 'BRB_' . uniqid();
        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => $codeBrb,
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();

        $user = User::factory()->create(['email' => 'owner_' . $id . '@acme.test', 'role' => 'user']);
        $companyUser = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'is_default' => true,
        ]);

        DB::table('company_user_branches')->insert([
            ['company_user_id' => $companyUser->id, 'branch_id' => $hqBranchId],
            ['company_user_id' => $companyUser->id, 'branch_id' => $branchBId],
        ]);

        return [$tenant->id, (int) $hqBranchId, (int) $branchBId, $user];
    }

    private function seedWarehouseAndVariants(int $hqBranchId, int $branchBId): array
    {
        $hqWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-HQ-' . uniqid(),
            'name' => 'HQ Central Warehouse',
            'warehouse_type' => 'general',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-BRB-' . uniqid(),
            'name' => 'Branch B Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Category ' . uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS ' . uniqid(),
            'code' => 'PCS' . uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'code' => 'PRD-' . uniqid(),
            'name' => 'Widget ' . uniqid(),
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variant1Id = DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'SKU-V1-' . uniqid(),
            'variant_name' => 'Variant 1',
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variant2Id = DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'SKU-V2-' . uniqid(),
            'variant_name' => 'Variant 2',
            'attributes' => json_encode(['color' => 'red']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$hqWarehouseId, $branchBWarehouseId, $variant1Id, $variant2Id];
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            DB::statement('DROP SCHEMA IF EXISTS "' . $schemaName . '" CASCADE');
        } catch (\Throwable $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('public', 'information_schema') AND schema_name NOT LIKE 'pg_%'"
            );
            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;
                $this->dropSchema($schemaName);
            }
        } catch (\Throwable $e) {
        }
    }
}
