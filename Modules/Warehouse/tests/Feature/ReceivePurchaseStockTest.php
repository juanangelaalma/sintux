<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Warehouse\Application\StockLayer\ReceivePurchaseStock;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockLayer;
use Modules\Warehouse\Models\StockMovement;
use Tests\TestCase;

class ReceivePurchaseStockTest extends TestCase
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

    public function test_receives_purchase_stock_into_warehouse(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'WH-PUR-'.uniqid(),
            'name' => 'Purchasing Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [$productId, $variantId] = $this->createProductAndVariant($branchId, 'PRD-PUR-'.uniqid());

        $referenceId = 42;

        app(ReceivePurchaseStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty' => 25,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => $referenceId,
            'reference_type' => 'Modules\Purchasing\Models\GoodsReceipt',
        ]);

        $layer = StockLayer::query()
            ->where('source_type', 'purchase_order')
            ->where('source_id', $referenceId)
            ->first();

        $this->assertNotNull($layer, 'Stock layer with purchase_order source should exist.');
        $this->assertEquals(25, (float) $layer->qty_remaining);
        $this->assertEquals(10000, (float) $layer->unit_cost);

        $balance = StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->first();

        $this->assertNotNull($balance);
        $this->assertEquals(25, (float) $balance->qty_on_hand);

        $movement = StockMovement::query()
            ->where('movement_type', 'purchase_in')
            ->where('reference_type', 'Modules\Purchasing\Models\GoodsReceipt')
            ->where('reference_id', $referenceId)
            ->first();

        $this->assertNotNull($movement, 'purchase_in stock movement should exist.');
        $this->assertEquals(25, (float) $movement->qty);
        $this->assertEquals(10000, (float) $movement->unit_cost);
        $this->assertEquals($layer->id, $movement->stock_layer_id);

        tenancy()->end();
    }

    /**
     * @return array{0: string|int, 1: int}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('rps_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Receive Purchase Test-'.$id,
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

        return [$tenant->id, (int) $branchId];
    }

    /**
     * @return array{0: int, 1: int} [productId, variantId]
     */
    private function createProductAndVariant(int $branchId, string $sku): array
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Category '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'Unit '.uniqid(),
            'code' => 'UOM-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'P-'.$sku,
            'name' => 'Product '.$sku,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'product_type' => 'simple',
            'is_purchased' => true,
            'is_sold' => true,
            'is_inventory_tracked' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => $sku,
            'variant_name' => 'Default',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$productId, $variantId];
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

            DB::statement(
                'DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE'
            );
        } catch (\Throwable $e) {
        }
    }

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

                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
