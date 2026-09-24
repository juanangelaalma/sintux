<?php

namespace Modules\Purchasing\Application\PurchaseReturn;

use Illuminate\Validation\ValidationException;
use Modules\Product\Application\Variant\GetVariantReturnProfile;
use Modules\Purchasing\Models\PurchaseInvoice;

class GetReturnableItems
{
    public function __construct(
        private readonly GetVariantReturnProfile $returnProfiles,
    ) {}

    /**
     * Baris faktur beserta sisa qty yang masih bisa diretur.
     * Untuk prefill form retur (satu faktur, berkali-kali s.d. qty 0).
     *
     * @return array{invoice: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public function execute(int $invoiceId): array
    {
        $invoice = PurchaseInvoice::with('items')->find($invoiceId);

        if (! $invoice) {
            throw ValidationException::withMessages([
                'purchase_invoice_id' => 'Faktur pembelian tidak ditemukan.',
            ]);
        }

        $items = array_values($invoice->items->map(function ($item): array {
            $profile = $this->returnProfiles->execute((int) $item->product_variant_id);

            return [
                'purchase_invoice_item_id' => (int) $item->id,
                'product_variant_id' => (int) $item->product_variant_id,
                'product_name' => (string) $item->product_name,
                'sku' => (string) $item->sku,
                'uom_name' => $item->uom_name,
                'qty' => (float) $item->qty,
                'qty_returned' => (float) ($item->qty_returned ?? 0),
                'remaining_qty' => max(0.0, (float) $item->qty - (float) ($item->qty_returned ?? 0)),
                'unit_price' => (float) $item->unit_price,
                'tax_id' => $item->tax_id !== null ? (int) $item->tax_id : null,
                'tax_rate' => (float) $item->tax_rate,
                'is_tracked' => $profile ? (bool) $profile['is_tracked'] : true,
            ];
        })->all());

        return [
            'invoice' => [
                'id' => (int) $invoice->id,
                'number' => (string) $invoice->number,
                'supplier_id' => (int) $invoice->supplier_id,
                'status' => $invoice->status->value,
                'is_tax_inclusive' => (bool) $invoice->is_tax_inclusive,
                'total' => (float) $invoice->total,
                'paid_amount' => (float) ($invoice->paid_amount ?? 0),
                'returned_amount' => (float) ($invoice->returned_amount ?? 0),
            ],
            'items' => $items,
        ];
    }
}
