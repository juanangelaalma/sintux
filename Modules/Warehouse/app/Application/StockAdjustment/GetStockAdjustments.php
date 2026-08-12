<?php

namespace Modules\Warehouse\Application\StockAdjustment;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Warehouse\Models\StockAdjustment;

class GetStockAdjustments
{
    /**
     * @param  list<int>  $accessibleBranchIds
     * @param  array{warehouse_id?: int, type?: string, status?: string, search?: string}  $filters
     */
    public function execute(array $accessibleBranchIds, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StockAdjustment::with([
            'warehouse.branch',
            'adjustedBy',
            'items.productVariant.product',
        ])
        ->whereHas('warehouse', function ($q) use ($accessibleBranchIds) {
            $q->whereIn('branch_id', $accessibleBranchIds);
        });

        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('adjustment_number', 'ilike', "%{$search}%")
                  ->orWhere('note', 'ilike', "%{$search}%")
                  ->orWhereHas('warehouse', fn ($w) => $w->where('name', 'ilike', "%{$search}%"));
            });
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }
}
