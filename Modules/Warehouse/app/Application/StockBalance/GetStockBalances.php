<?php

namespace Modules\Warehouse\Application\StockBalance;

use Modules\Warehouse\Models\StockBalance;

class GetStockBalances
{
    /**
     * @param  list<int>  $branchIds
     * @param  array<string, mixed>  $filters
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
     * Aggregate stock counts by warehouse ids for Product stats.
     *
     * @param  list<int>  $warehouseIds
     * @return array{available_count: int, low_stock_count: int, out_of_stock_count: int}
     */
    public function aggregateCountsByWarehouseIds(array $warehouseIds, int $lowStockThreshold = 5): array
    {
        $stockStats = StockBalance::whereIn('warehouse_id', $warehouseIds)
            ->selectRaw('
                COUNT(CASE WHEN qty_on_hand > ? THEN 1 END) as available_count,
                COUNT(CASE WHEN qty_on_hand > 0 AND qty_on_hand <= ? THEN 1 END) as low_stock_count,
                COUNT(CASE WHEN qty_on_hand = 0 THEN 1 END) as out_of_stock_count
            ', [$lowStockThreshold, $lowStockThreshold])
            ->first();

        return [
            'available_count' => (int) ($stockStats->available_count ?? 0),
            'low_stock_count' => (int) ($stockStats->low_stock_count ?? 0),
            'out_of_stock_count' => (int) ($stockStats->out_of_stock_count ?? 0),
        ];
    }
}
