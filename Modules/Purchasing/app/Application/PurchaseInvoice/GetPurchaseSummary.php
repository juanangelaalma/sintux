<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Support\Carbon;
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
            ->whereNotIn('status', [PurchaseInvoiceStatus::Paid->value, PurchaseInvoiceStatus::Cancelled->value]);

        $unpaidCount = (int) (clone $unpaidQuery)->count();
        $unpaidTotal = (float) (clone $unpaidQuery)->sum('total');

        $overdueQuery = PurchaseInvoice::whereIn('branch_id', $accessibleBranchIds)
            ->whereNotIn('status', [PurchaseInvoiceStatus::Paid->value, PurchaseInvoiceStatus::Cancelled->value])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today);

        $overdueCount = (int) (clone $overdueQuery)->count();
        $overdueTotal = (float) (clone $overdueQuery)->sum('total');

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
}
