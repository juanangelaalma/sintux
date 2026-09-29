<?php

namespace Modules\Purchasing\Application\SupplierMemo;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\SupplierDebitMemo;

class ApplyDebitMemo
{
    /**
     * Pakai sisa Debit Memo sebagai kredit supplier (pengurang bayar).
     *
     * Public cross-module API milik Purchasing. Idempoten per reference:
     * pemanggilan ulang dengan reference yang sama mengembalikan memo
     * tanpa mengurangi remaining dua kali.
     *
     * @throws ValidationException bila amount melebihi sisa
     */
    public function execute(int $memoId, float $amount, string $referenceType = '', int $referenceId = 0): SupplierDebitMemo
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Jumlah pemakaian debit memo harus lebih dari 0.',
            ]);
        }

        return DB::transaction(function () use ($memoId, $amount, $referenceType, $referenceId): SupplierDebitMemo {
            $memo = SupplierDebitMemo::whereKey($memoId)->lockForUpdate()->firstOrFail();

            if ($referenceType !== '' && $referenceId > 0) {
                $existing = DB::table('supplier_debit_memo_applies')
                    ->where('supplier_debit_memo_id', $memoId)
                    ->where('reference_type', $referenceType)
                    ->where('reference_id', $referenceId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $memo;
                }
            }

            if ($amount - (float) $memo->remaining > 0.0001) {
                throw ValidationException::withMessages([
                    'memo' => 'Pemakaian debit memo melebihi sisa kredit ('.number_format((float) $memo->remaining, 4, '.', '').').',
                ]);
            }

            $remaining = round((float) $memo->remaining - $amount, 4);

            $memo->update([
                'remaining' => $remaining,
                'status' => $remaining <= 0.005 ? 'applied' : 'open',
            ]);

            if ($referenceType !== '' && $referenceId > 0) {
                DB::table('supplier_debit_memo_applies')->insert([
                    'supplier_debit_memo_id' => $memoId,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'amount' => round($amount, 4),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $memo;
        });
    }
}
