<?php

namespace Modules\Sales\Application\SalesInvoice;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Sales\Models\SalesInvoice;

class GetSalesInvoices
{
    /**
     * @param  list<int>  $branchIds
     * @param  array{status?: string, search?: string}  $filters
     * @return LengthAwarePaginator<int, SalesInvoice>
     */
    public function execute(array $branchIds, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = SalesInvoice::with(['items'])
            ->whereIn('branch_id', $branchIds);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = strtolower((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(customer_name) LIKE ?', ["%{$search}%"]);
            });
        }

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }
}
