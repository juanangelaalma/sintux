<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Warehouse\Models\StockTransfer;

class GetStockTransfers
{
    /**
     * @param  list<int>  $accessibleBranchIds
     * @param  array{search?: string, status?: string, from_warehouse_id?: int, to_warehouse_id?: int}  $filters
     */
    public function execute(array $accessibleBranchIds, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StockTransfer::with([
            'fromWarehouse.branch',
            'toWarehouse.branch',
            'shippedBy',
            'receivedBy',
            'items.productVariant.product',
        ])
        ->where(function ($q) use ($accessibleBranchIds) {
            $q->whereHas('fromWarehouse', fn ($w) => $w->whereIn('branch_id', $accessibleBranchIds))
              ->orWhereHas('toWarehouse', fn ($w) => $w->whereIn('branch_id', $accessibleBranchIds));
        });

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_warehouse_id'])) {
            $query->where('from_warehouse_id', $filters['from_warehouse_id']);
        }

        if (! empty($filters['to_warehouse_id'])) {
            $query->where('to_warehouse_id', $filters['to_warehouse_id']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('fromWarehouse', fn ($w) => $w->where('name', 'ilike', "%{$search}%"))
                  ->orWhereHas('toWarehouse', fn ($w) => $w->where('name', 'ilike', "%{$search}%"));
            });
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }
}