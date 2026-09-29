<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Warehouse\Application\StockLayer\ReceivePurchaseStock;
use Modules\Warehouse\Application\StockTransfer\ReceiveStockTransfer;
use Modules\Warehouse\Models\StockLayer;
use Tests\TestCase;

class StockLineageTest extends TestCase
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

    public function test_receive_purchase_stock_sets_root_to_source(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->seedStock();

        tenancy()->initialize($tenantId);

        app(ReceivePurchaseStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty' => 10,
            'unit_cost' => 50000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 42,
        ]);

        $layer = StockLayer::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->firstOrFail();

        $this->assertSame('purchase_order', $layer->root_source_type);
        $this->assertSame(42, (int) $layer->root_source_id);
        $this->assertNull($layer->parent_layer_id);

        tenancy()->end();
    }

    public function test_received_transfer_layer_inherits_parent_and_root(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->seedStock();

        tenancy()->initialize($tenantId);

        // Layer asal PO 42 di gudang tujuan transfer.
        $sourceLayer = StockLayer::create([
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'qty_remaining' => 5,
            'unit_cost' => 50000,
            'received_at' => now()->subDay(),
            'source_type' => 'purchase_order',
            'source_id' => 42,
            'root_source_type' => 'purchase_order',
            'root_source_id' => 42,
        ]);

        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => null,
            'from_warehouse_id' => $warehouseId,
            'to_warehouse_id' => $warehouseId,
            'number' => 'TRF-'.substr(uniqid(), -10),
            'status' => 'shipped',
            'created_by' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variantId,
            'qty' => 5,
            'qty_shipped' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_transfer_item_layers')->insert([
            'stock_transfer_item_id' => $itemId,
            'stock_layer_id' => $sourceLayer->id,
            'qty_taken' => 5,
            'unit_cost' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(ReceiveStockTransfer::class)->execute(
            (int) $transferId,
            [['stock_transfer_item_id' => $itemId, 'qty_received' => 5]],
            1
        );

        $derived = StockLayer::query()
            ->where('source_type', 'stock_transfer')
            ->where('source_id', $transferId)
            ->firstOrFail();

        $this->assertSame('purchase_order', $derived->root_source_type);
        $this->assertSame(42, (int) $derived->root_source_id);
        $this->assertSame((int) $sourceLayer->id, (int) $derived->parent_layer_id);

        tenancy()->end();
    }

    public function test_backfill_sets_root_for_legacy_layers(): void
    {
        [$tenantId, $warehouseId, $variantId] = $this->seedStock();

        tenancy()->initialize($tenantId);

        // Simulasi layer lama: root kosong, hanya source yang ada.
        $legacy = StockLayer::create([
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'qty_remaining' => 3,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 99,
        ]);

        DB::table('stock_layers')->where('id', $legacy->id)->update([
            'root_source_type' => null,
            'root_source_id' => null,
        ]);

        // Jalankan ulang pernyataan backfill (bagian data migrasi).
        DB::statement('
            UPDATE stock_layers
            SET root_source_type = source_type,
                root_source_id = source_id
            WHERE root_source_type IS NULL AND source_type IS NOT NULL
        ');

        $refreshed = StockLayer::find($legacy->id);
        $this->assertSame('purchase_order', $refreshed->root_source_type);
        $this->assertSame(99, (int) $refreshed->root_source_id);

        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function seedStock(): array
    {
        $id = uniqid('lin_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Lineage Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        tenancy()->initialize($tenant);

        $branchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ_'.uniqid(),
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'WH-LIN-'.uniqid(),
            'name' => 'Lineage Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Category '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS '.uniqid(),
            'code' => 'PCS'.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'PRD-'.uniqid(),
            'name' => 'Widget '.uniqid(),
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_inventory_tracked' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'SKU-'.uniqid(),
            'variant_name' => 'Variant '.uniqid(),
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        return [$tenant->id, (int) $warehouseId, (int) $variantId];
    }

    private function cleanupCentralTables(): void
    {
        foreach (['company_user_branches', 'company_users', 'tenants', 'users'] as $table) {
            try {
                DB::table($table)->delete();
            } catch (\Throwable $e) {
            }
        }
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            $safe = str_replace('"', '""', $schemaName);
            DB::statement('DROP SCHEMA IF EXISTS "'.$safe.'" CASCADE');
        } catch (\Throwable $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name FROM information_schema.schemata
                 WHERE schema_name NOT IN ('public', 'information_schema')
                 AND schema_name NOT LIKE 'pg_%'"
            );

            foreach ($schemas as $row) {
                if (str_starts_with($row->schema_name, 'sch_')) {
                    $this->dropSchema($row->schema_name);
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
