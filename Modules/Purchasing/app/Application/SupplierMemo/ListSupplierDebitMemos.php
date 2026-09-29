<?php

namespace Modules\Purchasing\Application\SupplierMemo;

use Modules\Purchasing\Models\SupplierDebitMemo;

class ListSupplierDebitMemos
{
    /**
     * Sisa Debit Memo supplier (kredit supplier) untuk form pembayaran.
     *
     * Public cross-module API milik Purchasing: konsumen (Payment) memakai
     * sisa kredit ini sebagai pengurang bayar, bukan menulis tabel memo.
     *
     * @param  list<int>  $branchIds
     * @return list<array{id: int, number: string, remaining: float, total: float}>
     */
    public function execute(int $supplierId, array $branchIds = []): array
    {
        $query = SupplierDebitMemo::query()
            ->where('supplier_id', $supplierId)
            ->where('remaining', '>', 0);

        if ($branchIds !== []) {
            $query->whereIn('branch_id', $branchIds);
        }

        return $query
            ->orderBy('id')
            ->get(['id', 'number', 'total', 'remaining'])
            ->map(fn (SupplierDebitMemo $memo): array => [
                'id' => (int) $memo->id,
                'number' => (string) $memo->number,
                'total' => (float) $memo->total,
                'remaining' => (float) $memo->remaining,
            ])
            ->all();
    }
}
