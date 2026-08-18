<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class ProductHubStockTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();
        $this->cleanupCentralTables();
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

        parent::tearDown();
    }

    public function test_product_list_total_stock_reflects_warehouse_qty_on_hand(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);

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
            'code' => 'LAPTOP-'.$suffix,
            'name' => 'Laptop Pro-'.$suffix,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'product_type' => 'single',
            'is_active' => true,
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'LAPTOP-'.$suffix,
            'variant_name' => 'Primary',
            'is_active' => true,
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'WH-HQ-'.$suffix,
            'name' => 'Gudang HQ-'.$suffix,
            'branch_id' => $branchId,
            'warehouse_type' => 'general',
            'is_active' => true,
        ]);

        DB::table('stock_balances')->insertGetId([
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'qty_on_hand' => 7,
        ]);

        tenancy()->end();

        tenancy()->initialize($tenantId);
        $probes = DB::table('products')->get(['id', 'name', 'product_type'])->all();
        $schema = DB::selectOne('SELECT current_schema() AS s');
        Log::debug('DEBUG test probe', [
            'tenant' => $tenantId,
            'schema' => $schema->s ?? null,
            'products' => $probes,
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->get(route('product.hub', ['tab' => 'items']));

        $response->assertStatus(200);

        $response->assertInertia(
            fn ($page) => $page
                ->component('Product/Index')
                ->has('products.data', 1)
                ->has(
                    'products.data.0',
                    fn ($product) => $product
                        ->where('id', $productId)
                        ->where('total_stock', 7)
                        ->etc()
                )
        );
    }

    public function test_bundle_product_total_stock_derived_from_components(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);

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

        $componentId = DB::table('products')->insertGetId([
            'code' => 'COMP-'.$suffix,
            'name' => 'A Komponen-'.$suffix,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'product_type' => 'single',
            'is_active' => true,
        ]);

        $componentVariantId = DB::table('product_variants')->insertGetId([
            'product_id' => $componentId,
            'sku' => 'COMP-'.$suffix,
            'variant_name' => 'Primary',
            'is_active' => true,
        ]);

        $bundleId = DB::table('products')->insertGetId([
            'code' => 'BUNDLE-'.$suffix,
            'name' => 'B Paket-'.$suffix,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'product_type' => 'bundle',
            'is_active' => true,
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'code' => 'WH-HQ-'.$suffix,
            'name' => 'Gudang HQ-'.$suffix,
            'branch_id' => $branchId,
            'warehouse_type' => 'general',
            'is_active' => true,
        ]);

        DB::table('stock_balances')->insertGetId([
            'product_variant_id' => $componentVariantId,
            'warehouse_id' => $warehouseId,
            'qty_on_hand' => 10,
        ]);

        DB::table('product_bundle_items')->insertGetId([
            'bundle_product_id' => $bundleId,
            'item_product_id' => $componentId,
            'quantity' => 2,
        ]);

        tenancy()->end();

        $response = $this->actingAs($user)->get(route('product.hub', ['tab' => 'items']));

        $response->assertStatus(200);

        $response->assertInertia(
            fn ($page) => $page
                ->component('Product/Index')
                ->has('products.data', 2)
                ->where('products.data.0.id', $componentId)
                ->where('products.data.0.total_stock', 10)
                ->where('products.data.1.id', $bundleId)
                ->where('products.data.1.total_stock', 5)
        );
    }

    private function createCompanyWithMember(): array
    {
        $id = uniqid('hub_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Product Hub Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $existingHq = DB::table('branches')->where('code', 'HQ')->value('id');

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

        return [$tenant->id, (int) $branchId, $user];
    }

    private function cleanupCentralTables(): void
    {
        $this->deleteIfTableExists('company_user_branches');
        $this->deleteIfTableExists('company_users');
        $this->deleteIfTableExists('tenants');
        $this->deleteIfTableExists('users');
    }

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

    private function dropSchema(string $schemaName): void
    {
        try {
            $safeSchemaName = str_replace('"', '""', $schemaName);

            DB::statement('DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE');
        } catch (\Throwable $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name
                 FROM information_schema.schemata
                 WHERE schema_name NOT IN ('public', 'information_schema')
                 AND schema_name NOT LIKE 'pg_%'"
            );

            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;

                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
