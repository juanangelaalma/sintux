<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Purchasing\Models\PurchaseInvoice;

class GetPurchaseInvoices
{
    /**
     * @param  list<int>  $accessibleBranchIds
     * @param  array{status?: string, search?: string}  $filters
     * @return LengthAwarePaginator<int, PurchaseInvoice>
     */
    public function execute(array $accessibleBranchIds, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PurchaseInvoice::with(['items'])
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
