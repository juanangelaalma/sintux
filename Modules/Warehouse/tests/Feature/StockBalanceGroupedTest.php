<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Modules\Warehouse\Application\StockBalance\GetStockBalances;
use Tests\TestCase;

class StockBalanceGroupedTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->activeSchemaName) {
            DB::statement('DROP SCHEMA IF EXISTS "'.$this->activeSchemaName.'" CASCADE');
            $this->activeSchemaName = null;
        }

        parent::tearDown();
    }

    public function test_merges_mirror_variants_with_same_sku_per_warehouse(): void
    {
        $id = uniqid('sbg_');
        $this->activeSchemaName = 'sch_'.$id;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Grouped Balance Test-'.$id,
            'schema_name' => $this->activeSchemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $branchId = DB::table('branches')->insertGetId([
            'name' => 'HQ', 'code' => 'HQ-'.$id, 'is_headquarters' => true, 'is_active' => true,
        ]);
        $branchJkt = DB::table('branches')->insertGetId([
            'name' => 'JKT', 'code' => 'JKT-'.$id, 'is_headquarters' => false, 'is_active' => true,
        ]);
        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId, 'code' => 'WH-'.$id, 'name' => 'Gudang',
            'warehouse_type' => 'regular', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Dua varian cermin di cabang berbeda: nama + SKU sama, id beda.
        // SKU unik per cabang (product_variants_branch_sku_unique).
        $variantA = $this->createVariant($branchId, 'Produk Sama', 'SKU-SAMA-'.$id);
        $variantB = $this->createVariant($branchJkt, 'Produk Sama', 'SKU-SAMA-'.$id);

        DB::table('stock_balances')->insert([
            ['warehouse_id' => $warehouseId, 'product_variant_id' => $variantA, 'qty_on_hand' => 200, 'created_at' => now(), 'updated_at' => now()],
            ['warehouse_id' => $warehouseId, 'product_variant_id' => $variantB, 'qty_on_hand' => 50, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $result = app(GetStockBalances::class)->executeGrouped([$branchId], []);

        $this->assertSame(1, $result['total']);

        $group = $result['data'][0];

        $this->assertSame('SKU-SAMA-'.$id, $group['sku']);
        $this->assertSame(250, $group['qty_on_hand']);
        $this->assertSame(2, $group['variant_count']);
        $this->assertEqualsCanonicalizing([$variantA, $variantB], array_column($group['variants'], 'product_variant_id'));
        $this->assertEqualsCanonicalizing(
            ['HQ-'.$id, 'JKT-'.$id],
            array_column($group['variants'], 'branch_code')
        );

        tenancy()->end();
    }

    public function test_search_still_matches_grouped_rows(): void
    {
        $id = uniqid('sbg2_');
        $this->activeSchemaName = 'sch_'.$id;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Grouped Search Test-'.$id,
            'schema_name' => $this->activeSchemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $branchId = DB::table('branches')->insertGetId([
            'name' => 'HQ', 'code' => 'HQ-'.$id, 'is_headquarters' => true, 'is_active' => true,
        ]);
        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId, 'code' => 'WH-'.$id, 'name' => 'Gudang',
            'warehouse_type' => 'regular', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $variantId = $this->createVariant($branchId, 'Barang Unik', 'SKU-UNIK-'.$id);
        DB::table('stock_balances')->insert([
            'warehouse_id' => $warehouseId, 'product_variant_id' => $variantId, 'qty_on_hand' => 7,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $found = app(GetStockBalances::class)->executeGrouped([$branchId], ['search' => 'SKU-UNIK-'.$id]);
        $this->assertSame(1, $found['total']);

        $missed = app(GetStockBalances::class)->executeGrouped([$branchId], ['search' => 'TIDAK-ADA-'.$id]);
        $this->assertSame(0, $missed['total']);

        tenancy()->end();
    }

    private function createVariant(int $branchId, string $productName, string $sku): int
    {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Kat '.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'Pcs', 'code' => 'PCS'.uniqid(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'branch_id' => $branchId, 'code' => 'P'.uniqid(), 'name' => $productName,
            'category_id' => $categoryId, 'uom_id' => $uomId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId, 'product_id' => $productId, 'sku' => $sku,
            'variant_name' => 'Default', 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
