<?php

namespace Modules\Warehouse\Application\StockBalance;

use Illuminate\Support\Facades\DB;
use Modules\Warehouse\Models\StockBalance;

class GetStockBalances
{
    /**
     * @param  list<int>  $branchIds
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function execute(array $branchIds, array $filters = []): array
    {
        $query = StockBalance::with(['productVariant.product', 'warehouse'])
            ->whereHas('warehouse', fn ($query) => $query->whereIn('branch_id', $branchIds));

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['search'])) {
            $query->whereHas('productVariant', function ($query) use ($filters): void {
                $query->where('sku', 'like', '%'.$filters['search'].'%')
                    ->orWhere('variant_name', 'like', '%'.$filters['search'].'%')
                    ->orWhereHas('product', function ($query) use ($filters): void {
                        $query->where('code', 'like', '%'.$filters['search'].'%')
                            ->orWhere('name', 'like', '%'.$filters['search'].'%');
                    });
            });
        }

        return $query->orderBy('warehouse_id')
            ->orderBy('product_variant_id')
            ->paginate(15)
            ->toArray();
    }

    /**
     * Saldo gabungan per gudang + SKU untuk tampilan ringkas.
     *
     * Varian cermin (nama/SKU sama, id beda antar cabang) digabung jadi
     * satu baris dengan qty dijumlah; rincian per varian (termasuk kode
     * cabang pemiliknya sebagai penanda alokasi) disertakan untuk
     * drill-down. Layer FIFO / Kartu Stok tetap per varian karena layer
     * costing tidak digabung. Paginasi dihitung per grup.
     *
     * @param  list<int>  $branchIds
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function executeGrouped(array $branchIds, array $filters = []): array
    {
        $query = DB::table('stock_balances as sb')
            ->join('product_variants as pv', 'pv.id', '=', 'sb.product_variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->join('warehouses as w', 'w.id', '=', 'sb.warehouse_id')
            ->join('branches as vb', 'vb.id', '=', 'pv.branch_id')
            ->whereIn('w.branch_id', $branchIds);

        if (! empty($filters['warehouse_id'])) {
            $query->where('sb.warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['search'])) {
            $search = '%'.$filters['search'].'%';
            $query->where(function ($query) use ($search): void {
                $query->where('pv.sku', 'like', $search)
                    ->orWhere('pv.variant_name', 'like', $search)
                    ->orWhere('p.code', 'like', $search)
                    ->orWhere('p.name', 'like', $search);
            });
        }

        $rows = $query
            ->groupBy('sb.warehouse_id', 'w.code', 'w.name', 'pv.sku')
            ->selectRaw('sb.warehouse_id, w.code as warehouse_code, w.name as warehouse_name, pv.sku')
            ->selectRaw('MAX(p.name) as product_name')
            ->selectRaw('SUM(sb.qty_on_hand) as qty_on_hand')
            ->selectRaw("json_agg(json_build_object('product_variant_id', pv.id, 'variant_name', pv.variant_name, 'qty_on_hand', sb.qty_on_hand, 'branch_code', vb.code) ORDER BY pv.id) as variants")
            ->orderBy('sb.warehouse_id')
            ->orderBy('pv.sku')
            ->paginate(15);

        $data = collect($rows->items())->map(function ($row): array {
            $variants = collect(json_decode($row->variants ?? '[]', true) ?? [])
                ->map(fn (array $variant): array => [
                    'product_variant_id' => (int) $variant['product_variant_id'],
                    'variant_name' => $variant['variant_name'],
                    'qty_on_hand' => (int) $variant['qty_on_hand'],
                    'branch_code' => $variant['branch_code'],
                ])
                ->values()
                ->all();

            return [
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse' => [
                    'id' => (int) $row->warehouse_id,
                    'code' => $row->warehouse_code,
                    'name' => $row->warehouse_name,
                ],
                'sku' => $row->sku,
                'product_name' => $row->product_name,
                'qty_on_hand' => (int) $row->qty_on_hand,
                'variant_count' => count($variants),
                'variants' => $variants,
            ];
        })->all();

        return [...$rows->toArray(), 'data' => $data];
    }

    /**
     * Sum on-hand quantity per product variant id.
     *
     * @param  array<int, int>  $variantIds
     * @param  list<int>|null  $branchIds  Restrict to warehouses in these branches. Null = all warehouses.
     * @return array<int, int> Variant id => total qty on hand.
     */
    public function totalQtyByVariantIds(array $variantIds, ?array $branchIds = null): array
    {
        if ($variantIds === []) {
            return [];
        }

        $query = StockBalance::query()
            ->whereIn('product_variant_id', $variantIds)
            ->selectRaw('product_variant_id, SUM(qty_on_hand) as total_qty')
            ->groupBy('product_variant_id');

        if ($branchIds !== null && $branchIds !== []) {
            $query->whereHas('warehouse', fn ($q) => $q->whereIn('branch_id', $branchIds));
        }

        return $query
            ->pluck('total_qty', 'product_variant_id')
            ->map(fn ($qty): int => (int) $qty)
            ->all();
    }

    /**
     * Aggregate stock counts by warehouse ids for Product stats.
     *
     * Counts distinct product variants (same variant stocked in several
     * warehouses counts once). Classification uses the variant's total
     * on-hand qty across the given warehouses.
     *
     * @param  list<int>  $warehouseIds
     * @return array{available_count: int, low_stock_count: int, out_of_stock_count: int}
     */
    public function aggregateCountsByWarehouseIds(array $warehouseIds, int $lowStockThreshold = 5): array
    {
        $perVariant = StockBalance::whereIn('warehouse_id', $warehouseIds)
            ->selectRaw('SUM(qty_on_hand) as total_qty')
            ->groupBy('product_variant_id');

        $stockStats = DB::query()->fromSub($perVariant, 'variant_stock')
            ->selectRaw(
                '
                COUNT(CASE WHEN total_qty > ? THEN 1 END) as available_count,
                COUNT(CASE WHEN total_qty > 0 AND total_qty <= ? THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN total_qty = 0 THEN 1 END) as out_of_stock_count
            ',
                [$lowStockThreshold, $lowStockThreshold]
            )
            ->first();

        return [
            'available_count' => (int) ($stockStats->available_count ?? 0),
            'low_stock_count' => (int) ($stockStats->low_stock_count ?? 0),
            'out_of_stock_count' => (int) ($stockStats->out_of_stock_count ?? 0),
        ];
    }
}
