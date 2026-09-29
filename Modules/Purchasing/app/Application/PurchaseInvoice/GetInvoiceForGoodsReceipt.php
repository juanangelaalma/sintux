<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Modules\Purchasing\Models\PurchaseInvoice;

/**
 * Faktur pembelian untuk satu penerimaan barang (GRN).
 *
 * Public cross-module API: GRN bersifat 1:1 dengan faktur (dijaga UNIQUE
 * index + guard CreatePurchaseInvoice), sehingga modul lain — mis.
 * Warehouse — bisa menelusuri asal pembelian dari referensi GRN tanpa
 * membaca tabel Purchasing langsung.
 *
 * @return array{id: int, number: string, status: string}|null
 */
class GetInvoiceForGoodsReceipt
{
    public function execute(int $goodsReceiptId): ?array
    {
        $invoice = PurchaseInvoice::query()
            ->where('goods_receipt_id', $goodsReceiptId)
            ->first(['id', 'number', 'status']);

        if (! $invoice) {
            return null;
        }

        return [
            'id' => (int) $invoice->id,
            'number' => (string) $invoice->number,
            'status' => $invoice->status->value,
        ];
    }
}
