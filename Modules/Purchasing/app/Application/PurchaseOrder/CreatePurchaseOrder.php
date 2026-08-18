<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\GetPurchaseTaxes;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Models\PurchaseOrder;

class CreatePurchaseOrder
{
    public function __construct(
        private readonly GetPurchaseVariants $purchaseVariants,
        private readonly GetPurchaseTaxes $getPurchaseTaxes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, string $branchCode): PurchaseOrder
    {
        $variants = collect($this->purchaseVariants->execute())->keyBy('id');
        $taxes = collect($this->getPurchaseTaxes->execute())->keyBy('id');

        return DB::transaction(function () use ($data, $branchCode, $variants, $taxes) {
            $sequence = PurchaseOrder::where('branch_id', $data['branch_id'])->count() + 1;
            $number = 'PO-'.$branchCode.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            $subtotal = 0.0;
            $taxAmount = 0.0;

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty_ordered'];
                $unitPrice = (float) $item['unit_price'];
                $lineSubtotal = $qty * $unitPrice;
                $subtotal += $lineSubtotal;

                $taxId = $item['tax_id'] ?? null;
                $taxRate = $taxId ? (float) ($taxes->get($taxId)['rate'] ?? 0) : 0.0;
                $taxAmount += $lineSubtotal * ($taxRate / 100);
            }

            $po = PurchaseOrder::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'source_request_id' => $data['source_request_id'] ?? null,
                'source_quote_id' => $data['source_quote_id'] ?? null,
                'status' => 'pending',
                'order_date' => $data['order_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'note' => $data['note'] ?? null,
                'currency_code' => 'IDR',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $subtotal + $taxAmount,
            ]);

            foreach ($data['items'] as $item) {
                $variant = $variants->get($item['product_variant_id']);
                $qty = (float) $item['qty_ordered'];
                $unitPrice = (float) $item['unit_price'];
                $taxId = $item['tax_id'] ?? null;
                $taxRate = $taxId ? (float) ($taxes->get($taxId)['rate'] ?? 0) : 0.0;
                $lineTotal = ($qty * $unitPrice) * (1 + ($taxRate / 100));

                $po->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'product_name' => $variant['product_name'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'uom_name' => $variant['uom_name'] ?? null,
                    'qty_ordered' => $qty,
                    'qty_received' => 0,
                    'unit_price' => $unitPrice,
                    'tax_id' => $taxId,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotal,
                ]);
            }

            return $po->load('items');
        });
    }
}
