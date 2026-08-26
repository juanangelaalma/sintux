<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\PurchaseInvoice;

class ValidateInvoiceQuantities
{
    public function execute(PurchaseInvoice $invoice): void
    {
        $invoice->loadMissing('items');

        foreach ($invoice->items as $item) {
            if ($item->purchase_order_item_id) {
                $poItem = DB::table('purchase_order_items')
                    ->where('id', $item->purchase_order_item_id)
                    ->first();

                if ($poItem) {
                    $qtyInvoiced = (float) $item->qty;
                    $qtyReceived = (float) $poItem->qty_received;

                    if ($qtyInvoiced > $qtyReceived) {
                        throw ValidationException::withMessages([
                            'invoice' => "Jumlah tagihan ({$qtyInvoiced}) melebihi jumlah barang yang telah diterima ({$qtyReceived}) untuk item {$item->product_name}.",
                        ]);
                    }
                }
            }
        }
    }
}
