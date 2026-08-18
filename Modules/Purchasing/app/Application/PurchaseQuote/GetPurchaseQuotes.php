<?php

namespace Modules\Purchasing\Application\PurchaseQuote;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Purchasing\Models\PurchaseQuote;

class GetPurchaseQuotes
{
    /**
     * Get paginated purchase quotes scoped to branch access.
     *
     * @param  list<int>  $accessibleBranchIds
     * @param  array{status?: string, search?: string}  $filters
     * @return LengthAwarePaginator<int, PurchaseQuote>
     */
    public function execute(array $accessibleBranchIds, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseQuote::with(['items'])
            ->whereIn('branch_id', $accessibleBranchIds);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('number', 'ilike', "%{$search}%")
                    ->orWhere('note', 'ilike', "%{$search}%");
            });
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }
}
