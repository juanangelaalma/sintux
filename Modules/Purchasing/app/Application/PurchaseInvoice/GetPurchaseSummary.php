<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Models\PurchaseInvoice;

class GetPurchaseSummary
{
    /**
     * @param  list<int>  $accessibleBranchIds
     * @return array{
     *     unpaid_total: float,
     *     unpaid_count: int,
     *     overdue_total: float,
     *     overdue_count: int,
     *     paid_recent_total: float,
     *     paid_recent_count: int
     * }
     */
    public function execute(array $accessibleBranchIds): array
    {
        $today = Carbon::today()->toDateString();
        $thirtyDaysAgo = Carbon::today()->subDays(30)->toDateTimeString();

        $unpaidQuery = PurchaseInvoice::whereIn('branch_id', $accessibleBranchIds)
            ->whereNotIn('status', [
                PurchaseInvoiceStatus::Paid->value,
                PurchaseInvoiceStatus::ClosedByReturn->value,
                PurchaseInvoiceStatus::Cancelled->value,
            ])
            ->whereRaw($this->outstandingSql().' > 0');

        $unpaidCount = (int) (clone $unpaidQuery)->count();
        $unpaidTotal = $this->sumOutstanding($unpaidQuery);

        $overdueQuery = PurchaseInvoice::whereIn('branch_id', $accessibleBranchIds)
            ->whereNotIn('status', [
                PurchaseInvoiceStatus::Paid->value,
                PurchaseInvoiceStatus::ClosedByReturn->value,
                PurchaseInvoiceStatus::Cancelled->value,
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->whereRaw($this->outstandingSql().' > 0');

        $overdueCount = (int) (clone $overdueQuery)->count();
        $overdueTotal = $this->sumOutstanding($overdueQuery);

        $paidRecentQuery = PurchaseInvoice::whereIn('branch_id', $accessibleBranchIds)
            ->where('status', PurchaseInvoiceStatus::Paid->value)
            ->where('updated_at', '>=', $thirtyDaysAgo);

        $paidRecentCount = (int) (clone $paidRecentQuery)->count();
        $paidRecentTotal = (float) (clone $paidRecentQuery)->sum('total');

        return [
            'unpaid_total' => $unpaidTotal,
            'unpaid_count' => $unpaidCount,
            'overdue_total' => $overdueTotal,
            'overdue_count' => $overdueCount,
            'paid_recent_total' => $paidRecentTotal,
            'paid_recent_count' => $paidRecentCount,
        ];
    }

    private function sumOutstanding(Builder $query): float
    {
        return (float) (clone $query)->sum(
            DB::raw('GREATEST('.$this->outstandingSql().', 0)'),
        );
    }

    private function outstandingSql(): string
    {
        return 'total - COALESCE(paid_amount, 0) - COALESCE(returned_amount, 0)';
    }
}
