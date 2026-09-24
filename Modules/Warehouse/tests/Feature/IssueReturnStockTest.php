<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Modules\Warehouse\Application\StockIssue\IssueReturnStock;
use Modules\Warehouse\Application\StockLayer\ReceivePurchaseStock;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockLayer;
use Modules\Warehouse\Models\StockMovement;
use Modules\Warehouse\Services\InsufficientStockException;
use Tests\TestCase;

class IssueReturnStockTest extends TestCase
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

    public function test_issue_consumes_fifo_and_records_movement(): void
    {
        [$tenantId, $branchId, $warehouseId, $variantId] = $this->seedStock();

        tenancy()->initialize($tenantId);

        $result = app(IssueReturnStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty' => 12,
            'reference_type' => 'purchase_return',
            'reference_id' => 101,
        ]);

        // FIFO: 10 @ 50000 habis + 2 @ 52000.
        $this->assertSame(12, $result['qty']);
        $this->assertEquals(10 * 50000 + 2 * 52000, $result['total_cost']);
        $this->assertCount(2, $result['layers']);

        $this->assertEquals(8, (int) StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->value('qty_on_hand'));

        $movements = StockMovement::query()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', 101)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $movements);
        $this->assertSame('purchase_return_out', $movements[0]->movement_type);
        $this->assertEquals(-10, (float) $movements[0]->qty);
        $this->assertEquals(50000, (float) $movements[0]->unit_cost);
        $this->assertEquals(-2, (float) $movements[1]->qty);

        tenancy()->end();
    }

    public function test_issue_with_insufficient_stock_throws_without_changes(): void
    {
        [$tenantId, $branchId, $warehouseId, $variantId] = $this->seedStock();

        tenancy()->initialize($tenantId);

        try {
            app(IssueReturnStock::class)->execute([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'qty' => 999,
                'reference_type' => 'purchase_return',
                'reference_id' => 102,
            ]);
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString((string) $variantId, $e->getMessage());
        }

        $this->assertEquals(20, (int) StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->value('qty_on_hand'));
        $this->assertEquals(20, (int) StockLayer::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->sum('qty_remaining'));
        $this->assertSame(0, StockMovement::query()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', 102)
            ->count());

        tenancy()->end();
    }

    public function test_issue_is_idempotent_per_reference(): void
    {
        [$tenantId, $branchId, $warehouseId, $variantId] = $this->seedStock();

        tenancy()->initialize($tenantId);

        $first = app(IssueReturnStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty' => 5,
            'reference_type' => 'purchase_return',
            'reference_id' => 103,
        ]);

        $second = app(IssueReturnStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty' => 5,
            'reference_type' => 'purchase_return',
            'reference_id' => 103,
        ]);

        $this->assertEquals($first['total_cost'], $second['total_cost']);
        $this->assertEquals(15, (int) StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->value('qty_on_hand'));
        $this->assertSame(1, StockMovement::query()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', 103)
            ->count());

        tenancy()->end();
    }

    public function test_issue_with_source_filter_consumes_only_transfer_layers(): void
    {
        [$tenantId, $branchId, $warehouseId, $variantId] = $this->seedStock();

        tenancy()->initialize($tenantId);

        // Layer transfer 6 @ 52000 (transfer #777) di samping stok reguler 20 pcs.
        $this->addTransferLayer($warehouseId, $variantId, 6, 52000, 777);

        $result = app(IssueReturnStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty' => 4,
            'reference_type' => 'purchase_return',
            'reference_id' => 104,
            'source_transfer_id' => 777,
        ]);

        $this->assertEquals(4 * 52000, $result['total_cost']);

        // Layer reguler utuh; layer transfer berkurang 4.
        $this->assertEquals(20, (int) StockLayer::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->where('source_type', 'purchase_order')
            ->sum('qty_remaining'));
        $this->assertEquals(2, (int) StockLayer::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->where('source_type', 'stock_transfer')
            ->where('source_id', 777)
            ->sum('qty_remaining'));

        tenancy()->end();
    }

    public function test_issue_with_source_filter_insufficient_throws_without_changes(): void
    {
        [$tenantId, $branchId, $warehouseId, $variantId] = $this->seedStock();

        tenancy()->initialize($tenantId);

        $this->addTransferLayer($warehouseId, $variantId, 6, 52000, 777);

        try {
            app(IssueReturnStock::class)->execute([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'qty' => 10,
                'reference_type' => 'purchase_return',
                'reference_id' => 105,
                'source_transfer_id' => 777,
            ]);
            $this->fail('Expected InsufficientStockException.');
        } catch (InsufficientStockException $e) {
            $this->assertStringContainsString('777', $e->getMessage());
        }

        // Semua layer utuh (reguler 20 + transfer 6).
        $this->assertEquals(26, (int) StockLayer::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->sum('qty_remaining'));
        $this->assertSame(0, StockMovement::query()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', 105)
            ->count());

        tenancy()->end();
    }

    private function addTransferLayer(int $warehouseId, int $variantId, int $qty, float $unitCost, int $transferId): void
    {
        StockLayer::create([
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'qty_remaining' => $qty,
            'unit_cost' => $unitCost,
            'received_at' => now(),
            'source_type' => 'stock_transfer',
            'source_id' => $transferId,
        ]);

        StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->increment('qty_on_hand', $qty);
    }

    /**
     * Stok awal: layer 10 @ 50000 + layer 10 @ 52000 = 20 pcs.
     *
     * @return array{0: string, 1: int, 2: int, 3: int}
     */
    private function seedStock(): array
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'WH-RET-'.uniqid(),
            'name' => 'Return Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        [$productId, $variantId] = $this->createProductAndVariant($branchId);

        tenancy()->end();

        tenancy()->initialize($tenantId);

        app(ReceivePurchaseStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty' => 10,
            'unit_cost' => 50000,
            'received_at' => now()->subDay(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
        ]);

        app(ReceivePurchaseStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty' => 10,
            'unit_cost' => 52000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 2,
        ]);

        tenancy()->end();

        return [$tenantId, (int) $branchId, (int) $warehouseId, (int) $variantId];
    }

    /**
     * @return array{0: string|int, 1: int}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('irs_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Issue Return Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $branchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        return [$tenant->id, (int) $branchId];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createProductAndVariant(int $branchId): array
    {
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

        return [$productId, $variantId];
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
