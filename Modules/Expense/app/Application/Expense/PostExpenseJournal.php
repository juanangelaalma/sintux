<?php

namespace Modules\Expense\Application\Expense;

use LogicException;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Application\Journal\RecordJournal;
use Modules\Accounting\Application\Journal\ResolvePayableAccount;
use Modules\Expense\Models\Expense;

/**
 * Jurnal otomatis untuk biaya (BR-08 bayar langsung, BR-09 bayar nanti).
 *
 * Dipanggil di dalam DB::transaction() milik CreateExpense, mengikuti pola
 * FinalizePurchasePayment dan FinalizePurchaseReturn: Expense menjurnal lewat
 * public API Accounting secara langsung, bukan lewat event.
 *
 * Sisi debit:
 *   Dr akun biaya per baris ....... amount_before_tax
 *   Dr akun pajak masukan ........ tax_amount
 *
 * Sisi kredit:
 *   Cr Kas/Bank (bayar langsung) .. total debit - pemotongan
 *   Cr Hutang usaha (bayar nanti) .. total debit - pemotongan
 *   Cr akun penampung pemotongan .. withholding_total
 *
 * Balance dijamin by construction (Q-06). Nilai tersimpan 4 desimal, tapi
 * RecordJournal memvalidasi 2 desimal, jadi sisi kredit DITURUNKAN dari
 * total debit 2 desimal dan bukan diambil dari grand_total. Tanpa ini,
 * biaya dengan DPP pecahan non-2-desimal akan ditolak RecordJournal.
 */
class PostExpenseJournal
{
    /**
     * Seed key akun pajak masukan. Preseden yang sama dipakai
     * FinalizePurchaseReturn dan FinalizePurchasePayment.
     */
    private const SEED_INPUT_TAX = 'accounting.coa.1404';

    public function __construct(
        private readonly RecordJournal $recordJournal,
        private readonly ResolvePayableAccount $payableAccounts,
        private readonly ChartOfAccountQuery $chartOfAccounts,
    ) {}

    public function execute(Expense $expense): void
    {
        $lines = $this->buildLines($expense);

        if (count($lines) < 2) {
            // Total nol: tidak ada yang perlu dijurnal. RecordJournal juga
            // menolak jurnal kurang dari 2 baris.
            return;
        }

        $this->recordJournal->execute([
            'branch_id' => (int) $expense->branch_id,
            'journal_date' => $expense->transaction_date->toDateString(),
            'reference_type' => 'expense',
            'reference_id' => (int) $expense->id,
            'memo' => 'Biaya '.$expense->number,
            'lines' => $lines,
        ]);
    }

    /**
     * @return list<array{account_id: int, debit: float, credit: float}>
     */
    private function buildLines(Expense $expense): array
    {
        // Baris dengan akun biaya sama digabung supaya jurnal tidak
        // berduplikasi ketika satu biaya memakai akun yang sama di beberapa baris.
        $debitByAccount = [];
        $taxAmount = 0.0;

        foreach ($expense->lines as $line) {
            $accountId = (int) $line->account_id;
            $debitByAccount[$accountId] = ($debitByAccount[$accountId] ?? 0.0) + (float) $line->amount_before_tax;
            $taxAmount += (float) $line->tax_amount;
        }

        $debitTotal = round((float) $expense->subtotal, 2) + round($taxAmount, 2);
        $withholding = round((float) $expense->withholding_total, 2);
        $creditToSettlement = round($debitTotal - $withholding, 2);

        $lines = [];

        foreach ($debitByAccount as $accountId => $amount) {
            $rounded = round($amount, 2);

            if ($rounded > 0.005) {
                $lines[] = ['account_id' => $accountId, 'debit' => $rounded, 'credit' => 0.0];
            }
        }

        if ($taxAmount > 0.005) {
            $lines[] = [
                'account_id' => $this->inputTaxAccountId(),
                'debit' => round($taxAmount, 2),
                'credit' => 0.0,
            ];
        }

        if ($creditToSettlement > 0.005) {
            $lines[] = [
                'account_id' => $this->settlementAccountId($expense),
                'debit' => 0.0,
                'credit' => $creditToSettlement,
            ];
        }

        if ($withholding > 0.005) {
            $lines[] = [
                'account_id' => (int) $expense->withholding_account_id,
                'debit' => 0.0,
                'credit' => $withholding,
            ];
        }

        return $lines;
    }

    /**
     * BR-09: bayar nanti diakui ke hutang usaha. Bayar langsung ke kas/bank
     * dari kolom "Bayar dari".
     */
    private function settlementAccountId(Expense $expense): int
    {
        if ((bool) $expense->is_pay_later) {
            return (int) $this->payableAccounts->execute((int) $expense->contact_id)->id;
        }

        return (int) $expense->pay_from_account_id;
    }

    private function inputTaxAccountId(): int
    {
        $account = $this->chartOfAccounts->findBySeedKey(self::SEED_INPUT_TAX);

        if (! $account) {
            throw new LogicException('Akun PPN Masukan (1404) tidak ditemukan. Jalankan seeder CoA tenant.');
        }

        return $account['id'];
    }
}
