<?php

namespace Modules\Warehouse\Application\StockRequest;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Warehouse\Models\StockRequest;

class GetStockRequests
{
    /**
     * Get paginated stock requests scoped to branch access.
     *
     * @param  list<int>  $accessibleBranchIds
     * @param  array{status?: string, search?: string}  $filters
     */
    public function execute(array $accessibleBranchIds, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = StockRequest::with([
            'requestingWarehouse.branch',
            'destinationWarehouse.branch',
            'requestedBy',
            'items.productVariant.product',
        ])
            ->whereHas('requestingWarehouse', function ($q) use ($accessibleBranchIds) {
                $q->whereIn('branch_id', $accessibleBranchIds);
            });

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('note', 'ilike', "%{$search}%")
                    ->orWhereHas('requestingWarehouse', fn ($w) => $w->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('destinationWarehouse', fn ($w) => $w->where('name', 'ilike', "%{$search}%"));
            });
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Count pending stock requests scoped to branch access.
     *
     * @param  list<int>  $accessibleBranchIds
     */
    public function pendingCountByBranchIds(array $accessibleBranchIds): int
    {
        return StockRequest::whereHas('requestingWarehouse', function ($q) use ($accessibleBranchIds) {
            $q->whereIn('branch_id', $accessibleBranchIds);
        })->where('status', 'pending')->count();
    }
}
