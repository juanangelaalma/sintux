<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Modules\Purchasing\Application\PurchaseReturn\GetReturnLineage;
use Modules\Warehouse\Application\StockLayer\GetLayerLineage;
use Tests\TestCase;

class ReturnLineageTest extends TestCase
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

    public function test_lineage_maps_return_items_to_stock_layer_chain(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Layer akar: PO 42.
        $rootLayerId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $ctx['variantId'],
            'warehouse_id' => $ctx['warehouseId'],
            'qty_remaining' => 5,
            'unit_cost' => 50000,
            'received_at' => now()->subDay(),
            'source_type' => 'purchase_order',
            'source_id' => 42,
            'root_source_type' => 'purchase_order',
            'root_source_id' => 42,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Layer turunan: hasil transfer dari root.
        $transferLayerId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $ctx['variantId'],
            'warehouse_id' => $ctx['warehouseId'],
            'qty_remaining' => 3,
            'unit_cost' => 50000,
            'received_at' => now(),
            'source_type' => 'stock_transfer',
            'source_id' => 7,
            'root_source_type' => 'purchase_order',
            'root_source_id' => 42,
            'parent_layer_id' => $rootLayerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $returnId = DB::table('purchase_returns')->insertGetId([
            'number' => 'RBL-LIN-01',
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'purchase_invoice_id' => $ctx['invoiceId'],
            'warehouse_id' => $ctx['warehouseId'],
            'status' => 'approved',
            'return_date' => '2026-09-23',
            'currency_code' => 'IDR',
            'subtotal' => 100000,
            'tax_amount' => 0,
            'total' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = DB::table('purchase_return_items')->insertGetId([
            'purchase_return_id' => $returnId,
            'purchase_invoice_item_id' => $ctx['invoiceItemId'],
            'product_variant_id' => $ctx['variantId'],
            'stock_variant_id' => $ctx['variantId'],
            'product_name' => 'Widget',
            'sku' => 'SKU-LIN',
            'qty' => 2,
            'unit_price' => 50000,
            'tax_rate' => 0,
            'line_total' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Movement retur menunjuk layer transfer.
        DB::table('stock_movements')->insert([
            'warehouse_id' => $ctx['warehouseId'],
            'product_variant_id' => $ctx['variantId'],
            'movement_type' => 'purchase_return_out',
            'qty' => -2,
            'unit_cost' => 50000,
            'stock_layer_id' => $transferLayerId,
            'reference_type' => 'purchase_return',
            'reference_id' => $returnId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $lineage = app(GetReturnLineage::class)->execute($returnId);

        $this->assertArrayHasKey($itemId, $lineage);

        $chain = $lineage[$itemId];
        $this->assertCount(2, $chain);
        $this->assertSame($transferLayerId, $chain[0]['layer_id']);
        $this->assertSame('stock_transfer', $chain[0]['source_type']);
        $this->assertSame($rootLayerId, $chain[1]['layer_id']);
        $this->assertSame('purchase_order', $chain[1]['root_source_type']);
        $this->assertSame(42, (int) $chain[1]['root_source_id']);

        tenancy()->end();
    }

    public function test_lineage_tolerates_cycle_in_parent_chain(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Dua layer saling menunjuk parent (data korup) → depth-bound
        // menghentikan loop.
        $aId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $ctx['variantId'],
            'warehouse_id' => $ctx['warehouseId'],
            'qty_remaining' => 1,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'root_source_type' => 'purchase_order',
            'root_source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $bId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $ctx['variantId'],
            'warehouse_id' => $ctx['warehouseId'],
            'qty_remaining' => 1,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'stock_transfer',
            'source_id' => 2,
            'root_source_type' => 'purchase_order',
            'root_source_id' => 1,
            'parent_layer_id' => $aId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_layers')->where('id', $aId)->update(['parent_layer_id' => $bId]);

        $chain = app(GetLayerLineage::class)->execute($aId);

        $this->assertCount(2, $chain);

        tenancy()->end();
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('rlin_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Return Lineage Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-RLIN-'.uniqid(),
            'name' => 'Lineage Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supplierId = DB::table('contacts')->insertGetId([
            'branch_id' => $hqBranchId,
            'type' => 'supplier',
            'name' => 'Supplier '.uniqid(),
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
            'branch_id' => $hqBranchId,
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
            'branch_id' => $hqBranchId,
            'product_id' => $productId,
            'sku' => 'SKU-LIN',
            'variant_name' => 'Variant '.uniqid(),
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = DB::table('purchase_invoices')->insertGetId([
            'number' => 'FBL-LIN-001',
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'status' => 'approved',
            'invoice_date' => '2026-09-23',
            'currency_code' => 'IDR',
            'subtotal' => 100000,
            'tax_amount' => 0,
            'total' => 100000,
            'returned_amount' => 0,
            'paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceItemId = DB::table('purchase_invoice_items')->insertGetId([
            'purchase_invoice_id' => $invoiceId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget',
            'sku' => 'SKU-LIN',
            'qty' => 5,
            'qty_returned' => 0,
            'unit_price' => 50000,
            'tax_rate' => 0,
            'line_total' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        return [
            'tenantId' => $tenant->id,
            'hqBranchId' => (int) $hqBranchId,
            'warehouseId' => (int) $warehouseId,
            'supplierId' => (int) $supplierId,
            'variantId' => (int) $variantId,
            'invoiceId' => (int) $invoiceId,
            'invoiceItemId' => (int) $invoiceItemId,
        ];
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
