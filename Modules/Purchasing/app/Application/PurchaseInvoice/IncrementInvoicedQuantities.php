<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Support\Facades\DB;

/**
 * Tambah counter tertagih saat faktur menjadi Approved.
 *
 * Dipakai jalur auto-final (tanpa rule) dan finalize approval.
 * Pending/Rejected/Cancelled tidak menyentuh counter.
 */
class IncrementInvoicedQuantities
{
    /**
     * @param  list<array{goods_receipt_item_id?: int|null, purchase_order_item_id?: int|null, qty: float}>  $lines
     */
    public function execute(array $lines): void
    {
        $grnTotals = [];
        $poTotals = [];

        foreach ($lines as $line) {
            $qty = (float) $line['qty'];

            if (! empty($line['goods_receipt_item_id'])) {
                $id = (int) $line['goods_receipt_item_id'];
                $grnTotals[$id] = ($grnTotals[$id] ?? 0) + $qty;
            }

            if (! empty($line['purchase_order_item_id'])) {
                $id = (int) $line['purchase_order_item_id'];
                $poTotals[$id] = ($poTotals[$id] ?? 0) + $qty;
            }
        }

        foreach ($grnTotals as $id => $qty) {
            DB::table('goods_receipt_items')->where('id', $id)->increment('qty_invoiced', $qty);
        }

        foreach ($poTotals as $id => $qty) {
            DB::table('purchase_order_items')->where('id', $id)->increment('qty_invoiced', $qty);
        }
    }
}
