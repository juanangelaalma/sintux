<?php

namespace Modules\Accounting\Application\Journal;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;

class RecordJournal
{
    /**
     * Catat jurnal berimbang. Public cross-module API milik Accounting:
     * modul lain (Purchasing, Payment) menjurnal lewat entry point ini,
     * bukan ke tabel journals langsung.
     *
     * Aturan: tiap baris tepat satu sisi (debit xor kredit),
     * akun harus non-header dan tidak terhapus, total Dr = total Cr.
     *
     * @param  array{branch_id: int, journal_date: string, reference_type?: string|null, reference_id?: int|null, reversal_of_id?: int|null, memo?: string|null, lines: list<array{account_id: int, debit?: float, credit?: float, memo?: string|null}>}  $data
     */
    public function execute(array $data): Journal
    {
        $lines = $data['lines'] ?? [];

        if (count($lines) < 2) {
            throw ValidationException::withMessages([
                'lines' => 'Jurnal minimal terdiri dari 2 baris.',
            ]);
        }

        $accounts = ChartOfAccount::query()
            ->whereIn('id', collect($lines)->pluck('account_id')->map(fn ($id) => (int) $id)->all())
            ->where('is_header', false)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $index => $line) {
            $accountId = (int) ($line['account_id'] ?? 0);
            $debit = (float) ($line['debit'] ?? 0);
            $credit = (float) ($line['credit'] ?? 0);

            if (! in_array($accountId, $accounts, true)) {
                throw ValidationException::withMessages([
                    "lines.{$index}.account_id" => 'Akun tidak ditemukan, berupa akun header, atau sudah dihapus.',
                ]);
            }

            if ($debit < 0 || $credit < 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.amount" => 'Nominal debit/kredit tidak boleh negatif.',
                ]);
            }

            $hasDebit = $debit > 0;
            $hasCredit = $credit > 0;

            if ($hasDebit === $hasCredit) {
                throw ValidationException::withMessages([
                    "lines.{$index}.amount" => 'Tiap baris harus tepat satu sisi: debit ATAU kredit.',
                ]);
            }

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (round($totalDebit, 2) !== round($totalCredit, 2)) {
            throw ValidationException::withMessages([
                'lines' => 'Jurnal tidak balance (total debit harus sama dengan total kredit).',
            ]);
        }

        return DB::transaction(function () use ($data, $lines): Journal {
            $journal = Journal::create([
                'branch_id' => (int) $data['branch_id'],
                'journal_date' => $data['journal_date'],
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => isset($data['reference_id']) ? (int) $data['reference_id'] : null,
                'reversal_of_id' => isset($data['reversal_of_id']) ? (int) $data['reversal_of_id'] : null,
                'memo' => $data['memo'] ?? null,
                'status' => 'posted',
            ]);

            foreach ($lines as $line) {
                $journal->lines()->create([
                    'account_id' => (int) $line['account_id'],
                    'debit' => (float) ($line['debit'] ?? 0),
                    'credit' => (float) ($line['credit'] ?? 0),
                    'memo' => $line['memo'] ?? null,
                ]);
            }

            return $journal->load('lines');
        });
    }
}
