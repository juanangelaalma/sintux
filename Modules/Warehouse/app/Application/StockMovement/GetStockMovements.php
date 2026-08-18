<?php

namespace Modules\Warehouse\Application\StockMovement;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Warehouse\Models\StockMovement;

class GetStockMovements
{
    /**
     * Get paginated stock movement log (Kartu Stok) scoped to branch access.
     *
     * @param  list<int>  $accessibleBranchIds
     * @param  array{
     *     search?: string,
     *     warehouse_id?: int,
     *     product_variant_id?: int,
     *     movement_type?: string,
     *     date_from?: string,
     *     date_to?: string
     * }  $filters
     */
    public function execute(
        array $accessibleBranchIds,
        array $filters = [],
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = StockMovement::with([
            'warehouse.branch',
            'productVariant.product.uom',
            'stockLayer',
        ])
            ->whereHas('warehouse', fn ($w) => $w->whereIn('branch_id', $accessibleBranchIds));

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', (int) $filters['warehouse_id']);
        }

        if (! empty($filters['product_variant_id'])) {
            $query->where('product_variant_id', (int) $filters['product_variant_id']);
        }

        if (! empty($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('productVariant', function ($pv) use ($search) {
                    $pv->where('sku', 'ilike', "%{$search}%")
                        ->orWhere('variant_name', 'ilike', "%{$search}%")
                        ->orWhereHas('product', fn ($p) => $p->where('name', 'ilike', "%{$search}%"));
                })->orWhereHas('warehouse', fn ($w) => $w->where('name', 'ilike', "%{$search}%"));
            });
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }
}
