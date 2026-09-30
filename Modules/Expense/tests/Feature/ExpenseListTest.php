<?php

namespace Modules\Expense\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Expense\Application\Expense\GetExpenses;
use Modules\Expense\Application\Expense\GetExpenseSummary;

/**
 * Daftar biaya dan tiga kartu ringkasan (PRD §7.1, §11).
 */
class ExpenseListTest extends ExpenseDatabaseTestCase
{
    /**
     * Kartu "bulan ini" hanya menghitung biaya lunas: biaya belum lunas di
     * bulan yang sama tidak boleh masuk angkanya.
     */
    public function test_this_month_card_counts_only_settled_expenses(): void
    {
        $this->createExpense(['transaction_date' => now()->toDateString()]);
        $this->createExpense(['transaction_date' => now()->toDateString(), 'is_pay_later' => true]);

        $summary = $this->summary();

        $this->assertSame(1, $summary[GetExpenses::FILTER_THIS_MONTH]['count']);
        $this->assertGreaterThan(0.0, $summary[GetExpenses::FILTER_THIS_MONTH]['total']);
    }

    /**
     * Kartu 30 hari juga hanya menghitung biaya lunas, dan mencakup
     * transaksi sebelum hari ini.
     */
    public function test_last_30_days_card_counts_only_settled_expenses_within_window(): void
    {
        $this->createExpense(['transaction_date' => now()->subDays(10)->toDateString()]);
        $this->createExpense(['transaction_date' => now()->subDays(20)->toDateString(), 'is_pay_later' => true]);
        $this->createExpense(['transaction_date' => now()->subDays(90)->toDateString()]);

        $this->assertSame(1, $this->summary()[GetExpenses::FILTER_LAST_30_DAYS]['count']);
    }

    /**
     * Kartu belum dibayar menampilkan saldo, bukan total transaksi, dan
     * mencakup semua periode tanpa batasan tanggal.
     */
    public function test_unpaid_card_shows_outstanding_across_all_periods(): void
    {
        $this->createExpense(['transaction_date' => now()->subDays(200)->toDateString(), 'is_pay_later' => true]);
        $this->createExpense(['transaction_date' => now()->subDays(300)->toDateString(), 'is_pay_later' => true]);
        $this->createExpense(['transaction_date' => now()->toDateString()]);

        $unpaid = $this->summary()['unpaid'];

        $expected = (float) DB::table('expenses')
            ->where('status', 'open')
            ->sum(DB::raw('grand_total - amount_paid'));

        $this->assertSame(2, $unpaid['count']);
        $this->assertEqualsWithDelta(round($expected, 2), $unpaid['total'], 0.01);
    }

    /**
     * Klik kartu harus memfilter daftar dengan hasil yang sama dengan angka di
     * kartu. Ini yang menjamin angka kartu dan isi tabel tidak pernah berbeda.
     */
    public function test_clicking_summary_card_filters_list_with_same_totals(): void
    {
        $this->createExpense(['transaction_date' => now()->toDateString()]);
        $this->createExpense(['transaction_date' => now()->toDateString()]);
        $this->createExpense(['transaction_date' => now()->toDateString(), 'is_pay_later' => true]);

        $summary = $this->summary();
        $branchIds = [(int) $this->ctx['branchId']];

        foreach ([GetExpenses::FILTER_THIS_MONTH, GetExpenses::FILTER_LAST_30_DAYS] as $filter) {
            $rows = app(GetExpenses::class)->execute($branchIds, ['filter' => $filter], 50);

            $this->assertSame(
                $summary[$filter]['count'],
                $rows->total(),
                "Kartu {$filter} harus punya jumlah transaksi yang sama dengan daftar terfilter.",
            );

            $this->assertEqualsWithDelta(
                $summary[$filter]['total'],
                round((float) $rows->getCollection()->sum('grand_total'), 2),
                0.01,
                "Kartu {$filter} harus punya total yang sama dengan daftar terfilter.",
            );
        }

        $unpaidRows = app(GetExpenses::class)
            ->execute($branchIds, ['filter' => GetExpenses::FILTER_UNPAID], 50);

        $this->assertSame($summary['unpaid']['count'], $unpaidRows->total());
    }

    /**
     * Default urutan terbaru di atas.
     */
    public function test_list_is_ordered_newest_transaction_first(): void
    {
        $this->createExpense(['transaction_date' => '2026-01-10']);
        $this->createExpense(['transaction_date' => '2026-06-15']);
        $this->createExpense(['transaction_date' => '2026-03-20']);

        $rows = app(GetExpenses::class)
            ->execute([(int) $this->ctx['branchId']], [], 50)
            ->getCollection();

        // GetExpenses memetakan baris ke array untuk halaman daftar, jadi
        // transaction_date sudah berupa string Y-m-d.
        $this->assertSame(
            ['2026-06-15', '2026-03-20', '2026-01-10'],
            $rows->pluck('transaction_date')->all(),
        );
    }

    /**
     * Pencarian mencakup nomor biaya, kategori akun biaya, dan tag.
     */
    public function test_search_matches_number_account_category_and_tag(): void
    {
        $this->createExpense(['number' => 'EXP-SEWA-001']);
        $this->createExpense([
            'lines' => [[
                'account_id' => $this->ctx['expenseAccountId'],
                'description' => 'Listrik bulan ini',
                'tax_id' => null,
                'amount' => 75000,
            ]],
        ]);

        $accountName = (string) DB::table('chart_of_accounts')
            ->where('id', $this->ctx['expenseAccountId'])
            ->value('name');
        $tagId = (int) DB::table('expense_tags')->orderBy('id')->value('id');
        $tagName = (string) DB::table('expense_tags')->orderBy('id')->value('name');

        $this->createExpense(['tag_ids' => [$tagId]]);

        $branchIds = [(int) $this->ctx['branchId']];

        $byNumber = app(GetExpenses::class)->execute($branchIds, ['search' => 'SEWA'], 50);
        $this->assertSame(1, $byNumber->total());

        // Kategori di daftar adalah nama akun, bukan deskripsi baris.
        $byCategory = app(GetExpenses::class)
            ->execute($branchIds, ['search' => mb_substr($accountName, 0, 6)], 50);
        $this->assertSame(3, $byCategory->total(), 'Semua biaya memakai akun yang sama.');

        $byTag = app(GetExpenses::class)->execute($branchIds, ['search' => $tagName], 50);
        $this->assertSame(1, $byTag->total());

        $byNothing = app(GetExpenses::class)->execute($branchIds, ['search' => 'tidak-ada'], 50);
        $this->assertSame(0, $byNothing->total());
    }

    /**
     * Sisa tagihan nol untuk biaya lunas dan sebesar total untuk biaya
     * belum lunas.
     */
    public function test_outstanding_is_zero_when_settled_and_full_when_open(): void
    {
        $settled = $this->createExpense();
        $open = $this->createExpense(['is_pay_later' => true]);

        $this->assertEqualsWithDelta(0.0, $settled->outstanding(), 0.01);
        $this->assertEqualsWithDelta((float) $open->grand_total, $open->outstanding(), 0.01);
    }

    /**
     * Status default: closed untuk bayar langsung, open untuk bayar nanti.
     */
    public function test_status_counts_are_reported_per_branch(): void
    {
        $this->createExpense();
        $this->createExpense();
        $this->createExpense(['is_pay_later' => true]);

        $counts = app(GetExpenseSummary::class)->statusCounts([(int) $this->ctx['branchId']]);

        $this->assertSame(2, $counts['closed']);
        $this->assertSame(1, $counts['open']);
    }

    /**
     * @return array<string, array{total: float, count: int}>
     */
    private function summary(): array
    {
        return app(GetExpenseSummary::class)->execute([(int) $this->ctx['branchId']]);
    }
}
