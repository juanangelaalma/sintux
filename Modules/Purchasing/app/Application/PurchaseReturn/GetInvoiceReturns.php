<?php

namespace Modules\Purchasing\Application\PurchaseReturn;

use Modules\Purchasing\Models\PurchaseReturn;

class GetInvoiceReturns
{
    /**
     * Riwayat retur satu faktur untuk seksi Riwayat Retur di detail
     * faktur: tiap retur + total + memo + status.
     *
     * @return list<array{id: int, number: string, status: string, return_date: string, total: float, memo: string|null}>
     */
    public function execute(int $invoiceId): array
    {
        return PurchaseReturn::where('purchase_invoice_id', $invoiceId)
            ->orderBy('id')
            ->get(['id', 'number', 'status', 'return_date', 'total', 'memo'])
            ->map(fn (PurchaseReturn $purchaseReturn): array => [
                'id' => (int) $purchaseReturn->id,
                'number' => (string) $purchaseReturn->number,
                'status' => (string) $purchaseReturn->status,
                'return_date' => $purchaseReturn->return_date->toDateString(),
                'total' => (float) $purchaseReturn->total,
                'memo' => $purchaseReturn->memo,
            ])
            ->all();
    }
}
