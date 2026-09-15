<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Company\Models\CompanyUser;
use Modules\Warehouse\Application\StockLayer\ReceivePurchaseStock;
use Modules\Warehouse\Application\StockReservation\ConsumeReservedStock;
use Modules\Warehouse\Application\StockReservation\GetAvailableStock;
use Modules\Warehouse\Application\StockReservation\ReleaseStock;
use Modules\Warehouse\Application\StockReservation\ReserveStock;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockMovement;
use Modules\Warehouse\Models\StockReservation;
use Tests\TestCase;

class StockReservationTest extends TestCase
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

    public function test_available_stock_equals_on_hand_when_nothing_reserved(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $warehouseId = $this->createWarehouse($branchId, 'regular');
        [, $variantId] = $this->createProductAndVariant($branchId, 'RSV-AVL-'.uniqid());
        $this->receiveStock($warehouseId, $variantId, 25);

        $available = app(GetAvailableStock::class)->forItem($warehouseId, $variantId);

        $this->assertSame(25, $available);

        tenancy()->end();
    }

    public function test_reserve_reduces_available_stock(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $warehouseId = $this->createWarehouse($branchId, 'regular');
        [, $variantId] = $this->createProductAndVariant($branchId, 'RSV-RSV-'.uniqid());
        $this->receiveStock($warehouseId, $variantId, 25);

        app(ReserveStock::class)->execute(101, $warehouseId, [
            ['product_variant_id' => $variantId, 'qty' => 10],
        ]);

        $this->assertSame(15, app(GetAvailableStock::class)->forItem($warehouseId, $variantId));
        $this->assertSame(
            10,
            (int) StockReservation::query()
                ->where('sales_invoice_id', 101)
                ->where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)
                ->sum('qty')
        );

        // On-hand tidak bergerak saat reserve.
        $this->assertSame(25, (int) StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->value('qty_on_hand'));

        tenancy()->end();
    }

    public function test_reserve_beyond_available_throws_and_creates_nothing(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $warehouseId = $this->createWarehouse($branchId, 'regular');
        [, $variantId] = $this->createProductAndVariant($branchId, 'RSV-OVR-'.uniqid());
        $this->receiveStock($warehouseId, $variantId, 5);

        try {
            app(ReserveStock::class)->execute(102, $warehouseId, [
                ['product_variant_id' => $variantId, 'qty' => 6],
            ]);
            $this->fail('Reserve melebihi stok harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(0, StockReservation::query()->where('sales_invoice_id', 102)->count());
        $this->assertSame(5, app(GetAvailableStock::class)->forItem($warehouseId, $variantId));

        tenancy()->end();
    }

    public function test_release_restores_available_stock(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $warehouseId = $this->createWarehouse($branchId, 'regular');
        [, $variantId] = $this->createProductAndVariant($branchId, 'RSV-REL-'.uniqid());
        $this->receiveStock($warehouseId, $variantId, 25);

        app(ReserveStock::class)->execute(103, $warehouseId, [
            ['product_variant_id' => $variantId, 'qty' => 10],
        ]);
        $this->assertSame(15, app(GetAvailableStock::class)->forItem($warehouseId, $variantId));

        $released = app(ReleaseStock::class)->execute(103);

        $this->assertSame(1, $released);
        $this->assertSame(0, StockReservation::query()->where('sales_invoice_id', 103)->count());
        $this->assertSame(25, app(GetAvailableStock::class)->forItem($warehouseId, $variantId));

        tenancy()->end();
    }

    public function test_consume_decrements_balance_layers_and_clears_reservation(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $warehouseId = $this->createWarehouse($branchId, 'regular');
        [, $variantId] = $this->createProductAndVariant($branchId, 'RSV-CSM-'.uniqid());
        $this->receiveStock($warehouseId, $variantId, 25);

        app(ReserveStock::class)->execute(104, $warehouseId, [
            ['product_variant_id' => $variantId, 'qty' => 10],
        ]);

        app(ConsumeReservedStock::class)->execute(104, $warehouseId, [
            ['product_variant_id' => $variantId, 'qty' => 10],
        ], 'Modules\Sales\Models\SalesInvoice');

        $this->assertSame(15, (int) StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->value('qty_on_hand'));
        $this->assertSame(0, StockReservation::query()->where('sales_invoice_id', 104)->count());
        $this->assertSame(15, app(GetAvailableStock::class)->forItem($warehouseId, $variantId));

        $movement = StockMovement::query()
            ->where('movement_type', 'sales_out')
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->first();

        $this->assertNotNull($movement, 'sales_out movement harus tercatat.');
        $this->assertSame(-10, (int) $movement->qty);
        $this->assertSame('Modules\Sales\Models\SalesInvoice', $movement->reference_type);
        $this->assertSame(104, (int) $movement->reference_id);

        tenancy()->end();
    }

    public function test_consume_without_reservation_throws(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $warehouseId = $this->createWarehouse($branchId, 'regular');
        [, $variantId] = $this->createProductAndVariant($branchId, 'RSV-NRS-'.uniqid());
        $this->receiveStock($warehouseId, $variantId, 25);

        try {
            app(ConsumeReservedStock::class)->execute(105, $warehouseId, [
                ['product_variant_id' => $variantId, 'qty' => 5],
            ], 'Modules\Sales\Models\SalesInvoice');
            $this->fail('Consume tanpa reservasi harus ditolak.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(25, (int) StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->value('qty_on_hand'));

        tenancy()->end();
    }

    public function test_sale_warehouse_options_default_to_regular_of_branch(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $regularId = $this->createWarehouse($branchId, 'regular', 'GD-TST-REG');
        $consignmentId = $this->createWarehouse($branchId, 'consignment', 'GD-TST-KON');

        $all = app(GetWarehouses::class)->optionsForSale($branchId);

        $this->assertSame([$regularId, $consignmentId], array_column($all, 'id'));

        $regularOnly = app(GetWarehouses::class)->optionsForSale($branchId, 'regular');

        $this->assertSame([$regularId], array_column($regularOnly, 'id'));

        tenancy()->end();
    }

    private function createWarehouse(int $branchId, string $type, ?string $code = null): int
    {
        return (int) DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId,
            'code' => $code ?? 'WH-'.strtoupper($type).'-'.uniqid(),
            'name' => ucfirst($type).' Warehouse',
            'warehouse_type' => $type,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function receiveStock(int $warehouseId, int $variantId, int $qty): void
    {
        app(ReceivePurchaseStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty' => $qty,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
        ]);
    }

    /**
     * @return array{0: string|int, 1: int}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('rsv_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Stock Reservation Test-'.$id,
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
            'selling_price' => 15000,
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
