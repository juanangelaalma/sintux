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
}
