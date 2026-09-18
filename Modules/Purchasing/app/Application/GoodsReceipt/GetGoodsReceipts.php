<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Models\GoodsReceipt;

class GetGoodsReceipts
{
    /**
     * @param  list<int>  $branchIds
     * @param  array{status?: string, search?: string}  $filters
     * @return LengthAwarePaginator<int, GoodsReceipt>
     */
    public function execute(array $branchIds, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->baseQuery($branchIds, $filters);

        return $query->orderByDesc('id')->paginate($perPage)->withQueryString();
    }

    /**
     * Jumlah GRN menunggu keputusan HO — badge tab persetujuan.
     *
     * @param  list<int>  $branchIds
     */
    public function pendingSubmittedCount(array $branchIds): int
    {
        return $this->baseQuery($branchIds, ['status' => GoodsReceiptStatus::Submitted->value])->count();
    }

    /**
     * @param  list<int>  $branchIds
     * @param  array{status?: string, search?: string}  $filters
     */
    private function baseQuery(array $branchIds, array $filters = []): mixed
    {
        $query = GoodsReceipt::with(['purchaseOrder'])
            ->whereIn('branch_id', $branchIds);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $search = strtolower((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(number) LIKE ?', ["%{$search}%"])
                    ->orWhereRaw('LOWER(supplier_do_no) LIKE ?', ["%{$search}%"]);
            });
        }

        return $query;
    }
}
