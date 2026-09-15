<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\PurchaseInvoice;

/**
 * 3-way match kumulatif: total tertagih tidak boleh melewati
 * total diterima, baik per baris GRN maupun per baris PO.
 *
 * Menjaga invariansi: invoiced ≤ received ≤ ordered.
 */
class ValidateInvoiceQuantities
{
    /**
     * @param  list<array{index?: int, goods_receipt_item_id?: int|null, purchase_order_item_id?: int|null, qty: float|int, product_name?: string|null}>  $lines
     * @param  string|null  $itemKeyPrefix  Prefix key error per baris (mis. 'items'). Null = key 'invoice'.
     */
    public function execute(array $lines, ?string $itemKeyPrefix = null): void
    {
        $grnRequested = [];
        $poRequested = [];

        foreach ($lines as $pos => $line) {
            $index = $line['index'] ?? $pos;
            $qty = (float) $line['qty'];
            $name = (string) ($line['product_name'] ?? 'item');
            $grnItemId = ! empty($line['goods_receipt_item_id']) ? (int) $line['goods_receipt_item_id'] : null;
            $poItemId = ! empty($line['purchase_order_item_id']) ? (int) $line['purchase_order_item_id'] : null;

            if ($grnItemId) {
                $grnItem = DB::table('goods_receipt_items')->where('id', $grnItemId)->first();

                if ($grnItem) {
                    $already = (float) ($grnItem->qty_invoiced ?? 0) + ($grnRequested[$grnItemId] ?? 0);

                    if ($already + $qty > (float) $grnItem->qty_received) {
                        $this->fail($itemKeyPrefix, $index, "Jumlah tagihan ({$qty}) untuk {$name} melebihi sisa belum tertagih di GRN ini.");
                    }

                    $grnRequested[$grnItemId] = $already + $qty;
                }
            }

            if ($poItemId && ! $grnItemId) {
                $poItem = DB::table('purchase_order_items')->where('id', $poItemId)->first();

                if ($poItem) {
                    $already = (float) ($poItem->qty_invoiced ?? 0) + ($poRequested[$poItemId] ?? 0);

                    if ($already + $qty > (float) $poItem->qty_received) {
                        $this->fail($itemKeyPrefix, $index, "Jumlah tagihan ({$qty}) untuk {$name} melebihi jumlah barang yang telah diterima ({$poItem->qty_received}).");
                    }

                    $poRequested[$poItemId] = $already + $qty;
                }
            }
        }
    }

    public function executeForInvoice(PurchaseInvoice $invoice): void
    {
        $invoice->loadMissing('items');

        $lines = $invoice->items->map(fn ($item) => [
            'goods_receipt_item_id' => $item->goods_receipt_item_id,
            'purchase_order_item_id' => $item->purchase_order_item_id,
            'qty' => (float) $item->qty,
            'product_name' => $item->product_name,
        ])->all();

        $this->execute($lines);
    }

    private function fail(?string $prefix, int $index, string $message): void
    {
        if ($prefix) {
            throw ValidationException::withMessages([
                "{$prefix}.{$index}.qty" => $message,
            ]);
        }

        throw ValidationException::withMessages([
            'invoice' => $message,
        ]);
    }
}
