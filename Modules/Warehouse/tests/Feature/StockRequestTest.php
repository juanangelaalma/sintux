<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class StockRequestTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (tenancy()->initialized) {
            tenancy()->end();
        } /* * Remove leftover tenant schemas from previous failed tests. * * This is important because stock_requests, warehouses, * products, etc. live inside tenant schemas. */
        $this->dropLeftoverSchemas(); /* * Clean central tables in FK-safe order. * * stock_requests may reference users through: * stock_requests.requested_by -> users.id * * Therefore stock_requests must be removed before users. */
        $this->cleanupCentralTables();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        } /* * Drop the tenant schema created by this test. */
        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
        }
        parent::tearDown();
    }

    public function test_company_member_can_create_stock_request(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $hqWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $hqBranchId, 'code' => 'WH-HQ-'.uniqid(), 'name' => 'HQ Central Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branchBWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $branchBId, 'code' => 'WH-BRB-'.uniqid(), 'name' => 'Branch B Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        [$productId, $variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        tenancy()->end();
        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.store'), ['requesting_warehouse_id' => $branchBWarehouseId, 'destination_warehouse_id' => $hqWarehouseId, 'note' => 'Butuh pasokan tambahan barang', 'items' => [['product_variant_id' => $variantId, 'qty_requested' => 10]]]);
        $response->assertRedirect(route('warehouse.stock-requests.index'));
        tenancy()->initialize($tenantId);
        $stockRequest = DB::table('stock_requests')->where('requesting_warehouse_id', $branchBWarehouseId)->first();
        $this->assertNotNull($stockRequest);
        $this->assertSame('pending', $stockRequest->status);
        $this->assertSame('Butuh pasokan tambahan barang', $stockRequest->note);
        $items = DB::table('stock_request_items')->where('stock_request_id', $stockRequest->id)->get();
        $this->assertCount(1, $items);
        $this->assertEquals(10, $items->first()->qty_requested);
        tenancy()->end();
    }

    public function test_validation_rejects_qty_less_than_or_equal_zero(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $hqWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $hqBranchId, 'code' => 'WH-HQ-'.uniqid(), 'name' => 'HQ Central Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branchBWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $branchBId, 'code' => 'WH-BRB-'.uniqid(), 'name' => 'Branch B Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        [$productId, $variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        tenancy()->end();
        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.store'), ['requesting_warehouse_id' => $branchBWarehouseId, 'destination_warehouse_id' => $hqWarehouseId, 'items' => [['product_variant_id' => $variantId, 'qty_requested' => 0]]]);
        $response->assertSessionHasErrors(['items.0.qty_requested']);
    }

    public function test_validation_rejects_non_hq_destination_warehouse(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $branchCId = DB::table('branches')->insertGetId(['name' => 'Branch C', 'code' => 'BR-C-'.uniqid(), 'is_headquarters' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branchCWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $branchCId, 'code' => 'WH-BRC-'.uniqid(), 'name' => 'Branch C Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branchBWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $branchBId, 'code' => 'WH-BRB-'.uniqid(), 'name' => 'Branch B Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        [$productId, $variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        tenancy()->end();
        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.store'), ['requesting_warehouse_id' => $branchBWarehouseId, 'destination_warehouse_id' => $branchCWarehouseId, 'items' => [['product_variant_id' => $variantId, 'qty_requested' => 5]]]);
        $response->assertSessionHasErrors(['destination_warehouse_id']);
    }

    public function test_validation_rejects_same_requesting_and_destination_warehouse(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        $hqWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $hqBranchId, 'code' => 'WH-HQ-'.uniqid(), 'name' => 'HQ Central Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        [$productId, $variantId] = $this->createProductAndVariant($hqBranchId, 'PRD-001', true);
        tenancy()->end();
        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.store'), ['requesting_warehouse_id' => $hqWarehouseId, 'destination_warehouse_id' => $hqWarehouseId, 'items' => [['product_variant_id' => $variantId, 'qty_requested' => 5]]]);
        $response->assertSessionHasErrors(['requesting_warehouse_id']);
    }

    public function test_validation_rejects_duplicate_variant_items(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $hqWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $hqBranchId, 'code' => 'WH-HQ-'.uniqid(), 'name' => 'HQ Central Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branchBWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $branchBId, 'code' => 'WH-BRB-'.uniqid(), 'name' => 'Branch B Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        [$productId, $variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        tenancy()->end();
        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.store'), ['requesting_warehouse_id' => $branchBWarehouseId, 'destination_warehouse_id' => $hqWarehouseId, 'items' => [['product_variant_id' => $variantId, 'qty_requested' => 5], ['product_variant_id' => $variantId, 'qty_requested' => 10]]]);
        $response->assertSessionHasErrors(['items.0.product_variant_id', 'items.1.product_variant_id']);
    }

    public function test_validation_rejects_inactive_product_variant(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $hqWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $hqBranchId, 'code' => 'WH-HQ-'.uniqid(), 'name' => 'HQ Central Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $branchBWarehouseId = DB::table('warehouses')->insertGetId(['branch_id' => $branchBId, 'code' => 'WH-BRB-'.uniqid(), 'name' => 'Branch B Warehouse', 'warehouse_type' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        [$productId, $inactiveVariantId] = $this->createProductAndVariant($branchBId, 'PRD-INACTIVE', false);
        tenancy()->end();
        $response = $this->actingAs($user)->post(route('warehouse.stock-requests.store'), ['requesting_warehouse_id' => $branchBWarehouseId, 'destination_warehouse_id' => $hqWarehouseId, 'items' => [['product_variant_id' => $inactiveVariantId, 'qty_requested' => 5]]]);
        $response->assertSessionHasErrors(['items.0.product_variant_id']);
    }

    /** * Create tenant, branches and company member. */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('strq_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;
        $tenant = Tenant::create(['id' => $id, 'name' => 'Stock Request Test Corp', 'schema_name' => $schemaName, 'is_active' => true]);
        tenancy()->initialize($tenant);
        $existingHq = DB::table('branches')->where('code', 'HQ')->value('id');
        $hqBranchId = $existingHq ? (int) $existingHq : DB::table('branches')->insertGetId(['name' => 'HQ Branch', 'code' => 'HQ', 'is_headquarters' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $codeBrb = 'BRB_'.uniqid();
        $branchBId = DB::table('branches')->insertGetId(['name' => 'Branch B', 'code' => $codeBrb, 'is_headquarters' => false, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        tenancy()->end();
        $user = User::factory()->create(['email' => 'member_'.$id.'@acme.test', 'role' => 'user']);
        $companyUser = CompanyUser::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'role' => 'member', 'is_default' => true]);
        DB::table('company_user_branches')->insert([['company_user_id' => $companyUser->id, 'branch_id' => $hqBranchId], ['company_user_id' => $companyUser->id, 'branch_id' => $branchBId]]);

        return [$tenant->id, (int) $hqBranchId, (int) $branchBId, $user];
    }

    /** * Create product category, UOM, product and variant * inside the currently initialized tenant. */
    private function createProductAndVariant(int $branchId, string $codePrefix = 'PRD', bool $variantActive = true): array
    {
        $catId = DB::table('product_categories')->insertGetId(['name' => 'Category '.uniqid(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'PCS '.uniqid(), 'code' => 'PCS'.uniqid(), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $productId = DB::table('products')->insertGetId(['branch_id' => $branchId, 'code' => $codePrefix.'-'.uniqid(), 'name' => 'Widget '.uniqid(), 'category_id' => $catId, 'uom_id' => $uomId, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $variantId = DB::table('product_variants')->insertGetId(['branch_id' => $branchId, 'product_id' => $productId, 'sku' => 'SKU-'.uniqid(), 'variant_name' => 'Widget Variant '.uniqid(), 'attributes' => json_encode(['color' => 'blue']), 'is_active' => $variantActive, 'created_at' => now(), 'updated_at' => now()]);

        return [$productId, $variantId];
    }

    /** * Clean central tables while respecting foreign keys. */
    private function cleanupCentralTables(): void
    { /* * stock_requests may reference users.requested_by. * * If stock_requests is located in the public schema, * it must be deleted before users. * * CASCADE is intentionally NOT used here so that this test * exposes unexpected FK relationships instead of hiding them. */
        $this->deleteIfTableExists('stock_request_items');
        $this->deleteIfTableExists('stock_requests');
        $this->deleteIfTableExists('company_user_branches');
        $this->deleteIfTableExists('company_users'); /* * Tenants may be referenced by other central tables. * Delete tenants after their dependent company records. */
        $this->deleteIfTableExists('tenants'); /* * Users must be deleted last because other central tables * may reference users.id. */
        $this->deleteIfTableExists('users');
    }

    /** * Delete all records from a table only if it exists. */
    private function deleteIfTableExists(string $table): void
    {
        $exists = DB::selectOne('SELECT EXISTS ( SELECT 1 FROM information_schema.tables WHERE table_schema = current_schema() AND table_name = ? ) AS exists', [$table]);
        if ($exists && (bool) $exists->exists) {
            DB::table($table)->delete();
        }
    }

    /** * Drop a tenant schema. */
    private function dropSchema(string $schemaName): void
    {
        try { /* * Escape double quotes to prevent malformed identifiers. */
            $safeSchemaName = str_replace('"', '""', $schemaName);
            DB::statement('DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE');
        } catch (\Throwable $e) { /* * Cleanup failure should not hide the actual test failure. */
        }
    }

    /** * Drop leftover tenant schemas from previous failed tests. */
    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('public', 'information_schema') AND schema_name NOT LIKE 'pg_%'");
            foreach ($schemas as $row) {
                $schemaName = $row->schema_name; /* * Only remove schemas that look like test tenant schemas. * * This prevents accidentally deleting unrelated schemas * from the testing database. */
                if (str_starts_with($schemaName, 'sch_') || str_starts_with($schemaName, 'brand_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Throwable $e) { /* * Ignore cleanup errors. */
        }
    }
}
