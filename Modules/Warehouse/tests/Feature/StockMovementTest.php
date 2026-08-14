<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class StockMovementTest extends TestCase
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

        $this->seed(\Modules\Company\Database\Seeders\RolePermissionSeeder::class);
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
     * Stock movements list can be rendered and filtered.
     */
    public function test_stock_movements_index_and_layers_api(): void
    {
        [$tenantId, $hqBranchId, $user] = $this->createCompanyWithMember();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $hqBranchId,
        ]);

        tenancy()->initialize($tenantId);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-TEST-' . uniqid(),
            'name' => 'Test Warehouse',
            'warehouse_type' => 'general',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat ' . uniqid(),
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
            'name' => 'Movement Item ' . uniqid(),
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'SKU-MVT-' . uniqid(),
            'variant_name' => 'Variant Mvt ' . uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $layerId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'qty_remaining' => 10,
            'unit_cost' => 15000,
            'received_at' => now(),
            'source_type' => 'adjustment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_movements')->insert([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'movement_type' => 'adjustment_in',
            'qty' => 10,
            'unit_cost' => 15000,
            'stock_layer_id' => $layerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        /*
         * 1. Get stock movements page.
         */
        $response = $this->actingAs($user)
            ->get(route('warehouse.stock-movements.index'));

        $response->assertOk();

        /*
         * 2. Get active layers API endpoint.
         */
        $jsonResponse = $this->actingAs($user)
            ->get(route('warehouse.stock-layers.index', [
                'warehouse' => $warehouseId,
                'product_variant' => $variantId,
            ]));

        $jsonResponse->assertOk();
        $jsonResponse->assertJsonFragment([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
        ]);
    }

    private function createCompanyWithMember(): array
    {
        $id = uniqid('mvt_');
        $schemaName = 'sch_' . $id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Movement Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
        ]);

        tenancy()->initialize($tenant);

        $existingHq = DB::table('branches')
            ->where('code', 'HQ')
            ->value('id');

        $hqBranchId = $existingHq
            ? (int) $existingHq
            : DB::table('branches')->insertGetId([
                'name' => 'HQ Branch',
                'code' => 'HQ',
                'is_headquarters' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'owner_' . $id . '@acme.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'is_default' => true,
        ]);

        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $hqBranchId,
        ]);

        return [
            $tenant->id,
            (int) $hqBranchId,
            $user,
        ];
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            DB::statement('DROP SCHEMA IF EXISTS "' . $schemaName . '" CASCADE');
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
                WHERE schema_name NOT IN ('public', 'information_schema')
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
