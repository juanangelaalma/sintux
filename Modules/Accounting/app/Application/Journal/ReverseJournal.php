<?php

namespace Modules\Accounting\Application\Journal;

use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\Journal;

class ReverseJournal
{
    public function __construct(
        private readonly RecordJournal $recordJournal,
    ) {}

    public function execute(int $journalId, ?string $memo = null): Journal
    {
        $original = Journal::with('lines')->find($journalId);

        if (! $original) {
            throw ValidationException::withMessages([
                'journal_id' => 'Jurnal asal tidak ditemukan.',
            ]);
        }

        $lines = $original->lines->map(fn ($line): array => [
            'account_id' => (int) $line->account_id,
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
            'memo' => $line->memo,
        ])->all();

        return $this->recordJournal->execute([
            'branch_id' => (int) $original->branch_id,
            'journal_date' => now()->toDateString(),
            'reference_type' => $original->reference_type,
            'reference_id' => $original->reference_id,
            'reversal_of_id' => $original->id,
            'memo' => $memo ?? 'Reversal of journal #'.$original->id,
            'lines' => $lines,
        ]);
    }
}
