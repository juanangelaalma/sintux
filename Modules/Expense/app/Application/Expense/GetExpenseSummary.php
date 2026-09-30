<?php

namespace Modules\Expense\Application\Expense;

use Modules\Expense\Enums\ExpenseStatus;

/**
 * Tiga kartu ringkasan di header halaman daftar (PRD §7.1).
 *
 * Kartu 1 dan 2 hanya menghitung biaya lunas. Kartu 3 hanya menghitung
 * biaya belum lunas dan lintas semua periode, karena yang ditampilkan adalah
 * saldo bukan transaksi.
 *
 * Predikatnya diambil dari GetExpenses::applyFilter() supaya klik kartu
 * memfilter daftar dengan hasil yang sama persis dengan angka di kartu.
 */
class GetExpenseSummary
{
    public function __construct(
        private readonly GetExpenses $expenses,
    ) {}

    /**
     * @param  list<int>  $branchIds
     * @return array{
     *     this_month: array{total: float, count: int},
     *     last_30_days: array{total: float, count: int},
     *     unpaid: array{total: float, count: int}
     * }
     */
    public function execute(array $branchIds): array
    {
        return [
            GetExpenses::FILTER_THIS_MONTH => $this->expenses->summaryFor(
                $branchIds,
                GetExpenses::FILTER_THIS_MONTH,
            ),
            GetExpenses::FILTER_LAST_30_DAYS => $this->expenses->summaryFor(
                $branchIds,
                GetExpenses::FILTER_LAST_30_DAYS,
            ),
            'unpaid' => $this->expenses->unpaidSummary($branchIds),
        ];
    }

    /**
     * Jumlah biaya per status untuk kolom Status dan badge tab.
     *
     * @param  list<int>  $branchIds
     * @return array{open: int, closed: int}
     */
    public function statusCounts(array $branchIds): array
    {
        $counts = $this->expenses->countsByStatus($branchIds);

        return [
            'open' => $counts[ExpenseStatus::Open->value] ?? 0,
            'closed' => $counts[ExpenseStatus::Closed->value] ?? 0,
        ];
    }
}
