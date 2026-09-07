<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class StockBalanceViewTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        /*
         * Remove tenant schemas left behind by previously
         * failed/interrupted tests.
         */
        $this->dropLeftoverSchemas();

        /*
         * Clean central database tables in FK-safe order.
         */
        $this->cleanupCentralTables();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        /*
         * Remove the tenant schema created by this test.
         */
        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
            $this->activeSchemaName = null;
        }

        parent::tearDown();
    }

    public function test_stock_balance_list_is_read_only_and_shows_zero_qty(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchId,
        ]);

        tenancy()->initialize($tenantId);

        /*
         * Use unique values so this test never depends
         * on data left by another test.
         */
        $suffix = uniqid();

        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Elektronik-'.$suffix,
            'is_active' => true,
        ]);

        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'Piece-'.$suffix,
            'code' => 'PCS-'.$suffix,
            'is_active' => true,
        ]);

        $productId = DB::table('products')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'LAPTOP-'.$suffix,
            'name' => 'Laptop Pro-'.$suffix,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'is_active' => true,
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'LAPTOP-BLK-'.$suffix,
            'variant_name' => 'Black',
            'is_active' => true,
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'WH-HQ-'.$suffix,
            'name' => 'Gudang HQ-'.$suffix,
            'branch_id' => $branchId,
            'warehouse_type' => 'regular',
            'is_active' => true,
        ]);

        DB::table('stock_balances')->insertGetId([
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'qty_on_hand' => 0,
        ]);

        tenancy()->end();

        $response = $this->actingAs($user)
            ->get(route('warehouse.stock-balances.index', ['warehouse_id' => $warehouseId]));

        $response->assertStatus(200);

        $response->assertInertia(
            fn ($page) => $page
                ->component('Warehouse/StockBalances/index')
                ->has('balances.data', 1)
                ->has(
                    'balances.data.0',
                    fn ($balance) => $balance
                        ->where('qty_on_hand', 0)
                        ->where('warehouse_id', $warehouseId)
                        ->etc()
                )
        );
    }

    public function test_stock_balance_filtered_by_warehouse_branch_scope(): void
    {
        [$tenantId, $defaultBranchId, $user] = $this->createCompanyWithMember();

        $suffix = uniqid();

        tenancy()->initialize($tenantId);

        $nonHqBranchId = DB::table('branches')->insertGetId([
            'name' => 'Cabang A-'.$suffix,
            'code' => 'BR-A-'.$suffix,
            'is_headquarters' => false,
            'is_active' => true,
        ]);

        $companyUser = CompanyUser::where('user_id', $user->id)->where('tenant_id', $tenantId)->first();
        tenancy()->end();
        DB::table('company_user_branches')
            ->where('company_user_id', $companyUser->id)
            ->update(['branch_id' => $nonHqBranchId]);
        tenancy()->initialize($tenantId);

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $nonHqBranchId,
        ]);

        /*
         * Branch B should NOT be visible to the current
         * user's branch scope.
         */
        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Cabang B-'.$suffix,
            'code' => 'BR-B-'.$suffix,
            'is_headquarters' => false,
            'is_active' => true,
        ]);

        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Elektronik-'.$suffix,
            'is_active' => true,
        ]);

        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'Piece-'.$suffix,
            'code' => 'PCS-'.$suffix,
            'is_active' => true,
        ]);

        $productId = DB::table('products')->insertGetId([
            'branch_id' => $nonHqBranchId,
            'code' => 'PHONE-'.$suffix,
            'name' => 'Smartphone-'.$suffix,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'is_active' => true,
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $nonHqBranchId,
            'product_id' => $productId,
            'sku' => 'PHONE-BLK-'.$suffix,
            'variant_name' => 'Black',
            'is_active' => true,
        ]);

        $whBranchA = DB::table('warehouses')->insertGetId([
            'code' => 'WH-A-'.$suffix,
            'name' => 'Gudang A-'.$suffix,
            'branch_id' => $nonHqBranchId,
            'warehouse_type' => 'regular',
            'is_active' => true,
        ]);

        $whBranchB = DB::table('warehouses')->insertGetId([
            'code' => 'WH-B-'.$suffix,
            'name' => 'Gudang B-'.$suffix,
            'branch_id' => $branchBId,
            'warehouse_type' => 'regular',
            'is_active' => true,
        ]);

        DB::table('stock_balances')->insertGetId([
            'product_variant_id' => $variantId,
            'warehouse_id' => $whBranchA,
            'qty_on_hand' => 0,
        ]);

        DB::table('stock_balances')->insertGetId([
            'product_variant_id' => $variantId,
            'warehouse_id' => $whBranchB,
            'qty_on_hand' => 0,
        ]);

        tenancy()->end();

        $response = $this->actingAs($user)
            ->get(route('warehouse.stock-balances.index'));

        $response->assertStatus(200);

        $response->assertInertia(
            fn ($page) => $page
                ->component('Warehouse/StockBalances/index')
                ->has('balances.data', 1)
                ->has(
                    'balances.data.0',
                    fn ($balance) => $balance
                        ->where('warehouse_id', $whBranchA)
                        ->etc()
                )
        );
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('sb_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Stock Test Corp-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $existingHq = DB::table('branches')
            ->where('code', 'HQ')
            ->value('id');

        $branchId = $existingHq
            ? (int) $existingHq
            : DB::table('branches')->insertGetId([
                'name' => 'HQ Branch',
                'code' => 'HQ',
                'is_headquarters' => true,
                'is_active' => true,
            ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'member_'.$id.'@acme.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'is_default' => true,
        ]);

        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $branchId,
        ]);

        return [
            $tenant->id,
            (int) $branchId,
            $user,
        ];
    }

    /**
     * Remove central records that may have FK dependencies.
     */
    private function cleanupCentralTables(): void
    {
        /*
         * Tenant-scoped tables should normally disappear
         * when their schema is dropped.
         *
         * However, if some of these tables are located in
         * the central/public schema, clean them first.
         */
        $this->deleteIfTableExists('stock_request_items');
        $this->deleteIfTableExists('stock_requests');

        $this->deleteIfTableExists('company_user_branches');
        $this->deleteIfTableExists('company_users');

        $this->deleteIfTableExists('tenants');
        $this->deleteIfTableExists('users');
    }

    /**
     * Delete table contents only when the table exists
     * in the current schema.
     */
    private function deleteIfTableExists(string $table): void
    {
        $exists = DB::selectOne(
            'SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = current_schema()
                AND table_name = ?
            ) AS exists',
            [$table]
        );

        if ($exists && (bool) $exists->exists) {
            DB::table($table)->delete();
        }
    }

    /**
     * Drop a tenant schema.
     */
    private function dropSchema(string $schemaName): void
    {
        try {
            $safeSchemaName = str_replace('"', '""', $schemaName);

            DB::statement(
                'DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE'
            );
        } catch (\Throwable $e) {
            /*
             * Do not hide the actual test failure
             * because of cleanup failure.
             */
        }
    }

    /**
     * Remove tenant schemas left by failed tests.
     */
    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name
                 FROM information_schema.schemata
                 WHERE schema_name NOT IN (
                     'public',
                     'information_schema'
                 )
                 AND schema_name NOT LIKE 'pg_%'"
            );

            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;

                /*
                 * Only remove schemas created by tests.
                 */
                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Throwable $e) {
            /*
             * Ignore cleanup errors.
             */
        }
    }
}
