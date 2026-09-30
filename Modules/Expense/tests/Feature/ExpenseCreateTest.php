<?php

namespace Modules\Expense\Tests\Feature;

use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Journal;
use Modules\Expense\Application\Expense\GenerateExpenseNumber;
use Modules\Expense\Application\Expense\GetExpenseDetail;
use Modules\Expense\Models\Expense;

/**
 * Alur create: penomoran, status, jurnal, dan pembatasan. Setiap nama test
 * memuat ID BR.
 */
class ExpenseCreateTest extends ExpenseDatabaseTestCase
{
    /**
     * BR-08: bayar langsung mengisi amount_paid = grand_total, status closed,
     * sisa tagihan nol.
     */
    public function test_br_08_pay_directly_marks_expense_closed_with_zero_outstanding(): void
    {
        $expense = $this->createExpense();

        $this->assertSame('closed', $expense->status->value);
        $this->assertEqualsWithDelta((float) $expense->grand_total, (float) $expense->amount_paid, 0.0001);
        $this->assertEqualsWithDelta(0.0, $expense->outstanding(), 0.01);
    }

    /**
     * BR-08: bayar langsung menjurnal Dr akun beban dan PPN Masukan,
     * Cr Kas/Bank, dan jurnal balance.
     */
    public function test_br_08_pay_directly_posts_balanced_journal_debitting_expense_and_crediting_cash(): void
    {
        $expense = $this->createExpense();

        $journal = $this->journalFor($expense);

        $this->assertNotNull($journal, 'Biaya bayar langsung harus punya jurnal.');
        $this->assertSame('posted', $journal->status);
        $this->assertEquals(
            round((float) $journal->lines->sum('debit'), 2),
            round((float) $journal->lines->sum('credit'), 2),
            'Jurnal harus balance.',
        );

        $debitExpense = $journal->lines->firstWhere('account_id', $this->ctx['expenseAccountId']);
        $creditCash = $journal->lines->firstWhere('account_id', $this->ctx['cashAccountId']);
        $debitTax = $journal->lines->firstWhere('account_id', $this->ctx['inputTaxAccountId']);

        $this->assertEqualsWithDelta((float) $expense->subtotal, (float) $debitExpense->debit, 0.01);
        $this->assertGreaterThan(0.0, (float) $debitTax->debit, 'PPN Masukan harus di-debit.');
        $this->assertEqualsWithDelta((float) $expense->grand_total, (float) $creditCash->credit, 0.01);
    }

    /**
     * BR-09: bayar nanti mengisi amount_paid nol, status open, dan menjurnal
     * kredit ke hutang usaha (bukan ke kas/bank).
     */
    public function test_br_09_pay_later_marks_open_and_credits_accounts_payable(): void
    {
        $expense = $this->createExpense(['is_pay_later' => true]);

        $this->assertSame('open', $expense->status->value);
        $this->assertEqualsWithDelta(0.0, (float) $expense->amount_paid, 0.01);
        $this->assertEqualsWithDelta((float) $expense->grand_total, $expense->outstanding(), 0.01);

        $journal = $this->journalFor($expense);

        $this->assertNotNull($journal, 'Biaya bayar nanti harus punya jurnal.');
        $this->assertNotNull(
            $journal->lines->firstWhere('account_id', $this->ctx['payableAccountId']),
            'Hutang usaha harus dikreditkan.',
        );
        $this->assertNull(
            $journal->lines->firstWhere('account_id', $this->ctx['cashAccountId']),
            'Kas/Bank tidak boleh tersentuh untuk bayar nanti.',
        );
        $this->assertEquals(
            round((float) $journal->lines->sum('debit'), 2),
            round((float) $journal->lines->sum('credit'), 2),
        );
    }

    /**
     * BR-04: nomor kosong menghasilkan 10001 pada biaya pertama, lalu 10002.
     */
    public function test_br_04_auto_number_starts_at_10001_and_increments(): void
    {
        $first = $this->createExpense();
        $second = $this->createExpense();

        $this->assertSame('10001', $first->number);
        $this->assertSame(10001, $first->sequence);
        $this->assertSame('10002', $second->number);
        $this->assertSame(10002, $second->sequence);
    }

    /**
     * BR-04: penomoran tetap urut meski biaya pertama memakai nomor custom,
     * karena sequence terpisah dari number.
     */
    public function test_br_04_custom_number_does_not_break_auto_sequence(): void
    {
        $custom = $this->createExpense(['number' => 'EXP-2026-001']);
        $auto = $this->createExpense();

        $this->assertSame('EXP-2026-001', $custom->number);
        $this->assertSame(0, $custom->sequence, 'Nomor custom tidak memakai sequence otomatis.');
        $this->assertSame('10001', $auto->number);
    }

    /**
     * Nomor yang sama dipakai dua kali ditolak dengan pesan berbahasa Indonesia.
     */
    public function test_duplicate_number_is_rejected_with_indonesian_message(): void
    {
        $this->createExpense(['number' => 'EXP-001']);

        try {
            $this->createExpense(['number' => 'EXP-001']);
            $this->fail('Nomor duplikat seharusnya ditolak.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Nomor biaya sudah dipakai. Gunakan nomor lain.',
                $e->errors()['number'][0],
            );
        }

        $this->assertSame(1, Expense::query()->count(), 'Hanya biaya pertama yang boleh tersimpan.');
    }

    /**
     * BR-02: akun yang bukan bertipe beban ditolak.
     */
    public function test_br_02_rejects_account_outside_expense_account_types(): void
    {
        $this->expectException(ValidationException::class);

        $this->createExpense([
            'lines' => [[
                'account_id' => $this->ctx['revenueAccountId'],
                'description' => 'Pendapatan, bukan biaya',
                'tax_id' => null,
                'amount' => 50000,
            ]],
        ]);
    }

    /**
     * BR-03: tiga baris dengan akun berbeda disimpan semua dan daftar
     * menampilkan kategori -Terbagi-.
     */
    public function test_br_03_multiple_account_lines_are_stored_and_shown_as_divided(): void
    {
        $accountIds = array_slice($this->ctx['expenseAccountIds'], 0, 3);

        $this->assertGreaterThanOrEqual(3, count($accountIds), 'Butuh minimal 3 akun beban untuk tes ini.');

        $expense = $this->createExpense([
            'lines' => [
                ['account_id' => $accountIds[0], 'description' => 'Sewa', 'tax_id' => null, 'amount' => 100000],
                ['account_id' => $accountIds[1], 'description' => 'Listrik', 'tax_id' => null, 'amount' => 50000],
                ['account_id' => $accountIds[2], 'description' => 'Perjalanan', 'tax_id' => null, 'amount' => 30000],
            ],
        ]);

        $this->assertCount(3, $expense->lines);
        $this->assertEqualsWithDelta(180000.0, (float) $expense->grand_total, 0.01);

        $reloaded = app(GetExpenseDetail::class)
            ->execute((int) $expense->id, [(int) $this->ctx['branchId']]);

        $this->assertSame('-Terbagi-', $reloaded->categoryLabel());
    }

    /**
     * Satu akun biaya saja menampilkan nama akunnya, bukan -Terbagi-.
     */
    public function test_single_account_line_shows_account_name_as_category(): void
    {
        $expense = $this->createExpense();

        $reloaded = app(GetExpenseDetail::class)
            ->execute((int) $expense->id, [(int) $this->ctx['branchId']]);

        $this->assertNotSame('-Terbagi-', $reloaded->categoryLabel());
        $this->assertNotEmpty($reloaded->categoryLabel());
    }

    /**
     * Baris tanpa akun biaya yang lengkap ditolak.
     */
    public function test_line_without_valid_account_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->createExpense([
            'lines' => [['account_id' => 0, 'description' => null, 'tax_id' => null, 'amount' => 10000]],
        ]);
    }

    /**
     * Jumlah baris nol ditolak: biaya tanpa nilai tidak boleh dibuat.
     */
    public function test_zero_amount_line_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->createExpense([
            'lines' => [[
                'account_id' => $this->ctx['expenseAccountId'],
                'description' => null,
                'tax_id' => null,
                'amount' => 0,
            ]],
        ]);
    }

    /**
     * BR-05: pemotongan mengisi kolom withholding dan meng Kreditkan akun
     * penampung tanpa membuat jurnal tidak balance.
     */
    public function test_br_05_withholding_credits_the_supplied_account_and_keeps_journal_balanced(): void
    {
        $expense = $this->createExpense([
            'withholding' => [
                'type' => 'percent',
                'value' => 2,
                'account_id' => $this->ctx['expenseAccountId'],
            ],
        ]);

        $this->assertSame('percent', $expense->withholding_type->value);
        $this->assertGreaterThan(0.0, (float) $expense->withholding_total);

        $journal = $this->journalFor($expense);

        $this->assertEquals(
            round((float) $journal->lines->sum('debit'), 2),
            round((float) $journal->lines->sum('credit'), 2),
            'Pemotongan tidak boleh membuat jurnal tidak balance.',
        );
        $this->assertEqualsWithDelta(
            round((float) $expense->withholding_total, 2),
            round((float) $journal->lines->sum('credit') - (float) $expense->grand_total, 2),
            0.01,
            'Selisih kredit harus sama dengan pemotongan.',
        );
    }

    /**
     * BR-05: pemotongan tanpa akun penampung ditolak.
     */
    public function test_br_05_withholding_requires_supply_account(): void
    {
        $this->expectException(ValidationException::class);

        $this->createExpense([
            'withholding' => ['type' => 'nominal', 'value' => 1000, 'account_id' => null],
        ]);
    }

    /**
     * Pemotongan persen di luar 0-100 ditolak.
     */
    public function test_percent_withholding_outside_range_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        $this->createExpense([
            'withholding' => [
                'type' => 'percent',
                'value' => 150,
                'account_id' => $this->ctx['expenseAccountId'],
            ],
        ]);
    }

    /**
     * GenerateExpenseNumber membaca sequence tertinggi yang tersimpan, bukan
     * counter sendiri. Tabel kosong menghasilkan 10001; setelah satu biaya
     * tersimpan, panggilan berikutnya menghasilkan 10002.
     */
    public function test_generate_number_reads_highest_stored_sequence(): void
    {
        $generator = app(GenerateExpenseNumber::class);

        $this->assertSame(10001, $generator->execute()['sequence']);
        $this->assertSame('10001', $generator->execute()['number']);

        $this->createExpense();

        $this->assertSame(10002, $generator->execute()['sequence']);
    }

    private function journalFor(Expense $expense): ?Journal
    {
        return Journal::query()
            ->where('reference_type', 'expense')
            ->where('reference_id', (int) $expense->id)
            ->with('lines')
            ->first();
    }
}
