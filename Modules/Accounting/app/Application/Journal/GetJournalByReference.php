<?php

namespace Modules\Accounting\Application\Journal;

use Modules\Accounting\Models\Journal;

class GetJournalByReference
{
    /**
     * Jurnal berstatus posted beserta kaki + nama akun untuk tampilan
     * dokumen sumber (retur, pembayaran). Proyeksi halaman, bukan model
     * internal. Null bila belum ada (mis. retur masih pending).
     *
     * @return array{id: int, memo: string|null, journal_date: string, lines: list<array{account_code: string, account_name: string, debit: float, credit: float, memo: string|null}>}|null
     */
    public function execute(string $referenceType, int $referenceId): ?array
    {
        $journal = Journal::with('lines.account')
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('status', 'posted')
            ->orderBy('id')
            ->first();

        if (! $journal) {
            return null;
        }

        return [
            'id' => (int) $journal->id,
            'memo' => $journal->memo,
            'journal_date' => $journal->journal_date->toDateString(),
            'lines' => $journal->lines->map(fn ($line): array => [
                'account_code' => (string) ($line->account?->code ?? ''),
                'account_name' => (string) ($line->account?->name ?? ''),
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'memo' => $line->memo,
            ])->all(),
        ];
    }
}
