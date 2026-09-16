<?php

namespace Modules\Purchasing\Application\PurchaseQuote;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\TaxCalculator;
use Modules\Accounting\Application\TaxQuery;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Enums\PurchaseQuoteStatus;
use Modules\Purchasing\Models\PurchaseQuote;

class CreatePurchaseQuote
{
    public function __construct(
        private readonly GetPurchaseVariants $purchaseVariants,
        private readonly TaxQuery $taxQuery,
        private readonly TaxCalculator $taxCalculator,
    ) {}

    /**
     * Create a new purchase quote with snapshot product data.
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $branchIds  Branch scope for variant resolution. Null = all.
     */
    public function execute(array $data, string $branchCode, ?array $branchIds = null): PurchaseQuote
    {
        $variants = collect($this->purchaseVariants->execute($branchIds))->keyBy('id');
        $taxes = collect($this->taxQuery->listForPurchase())->keyBy('id');

        return DB::transaction(function () use ($data, $branchCode, $variants, $taxes) {
            $sequence = PurchaseQuote::where('branch_id', $data['branch_id'])->count() + 1;
            $number = 'QUOTE-'.$branchCode.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            $subtotal = 0.0;
            $taxAmount = 0.0;

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty'];
                $unitPrice = (float) $item['unit_price'];
                $lineSubtotal = $qty * $unitPrice;
                $subtotal += $lineSubtotal;

                $taxId = $item['tax_id'] ?? null;
                $taxDef = $taxId ? $taxes->get($taxId) : null;
                $taxAmount += $this->taxCalculator->calculate($lineSubtotal, $taxDef)['total'];
            }

            $quote = PurchaseQuote::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'source_request_id' => $data['source_request_id'] ?? null,
                'status' => PurchaseQuoteStatus::Draft,
                'quote_date' => $data['quote_date'],
                'valid_until' => $data['valid_until'] ?? null,
                'note' => $data['note'] ?? null,
                'currency_code' => 'IDR',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $subtotal + $taxAmount,
            ]);

            foreach ($data['items'] as $item) {
                $variant = $variants->get($item['product_variant_id']);
                $qty = (float) $item['qty'];
                $unitPrice = (float) $item['unit_price'];
                $taxId = $item['tax_id'] ?? null;
                $taxDef = $taxId ? $taxes->get($taxId) : null;
                $taxResult = $this->taxCalculator->calculate($qty * $unitPrice, $taxDef);
                $lineTotal = ($qty * $unitPrice) + $taxResult['total'];

                $quote->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'product_name' => $variant['product_name'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'uom_name' => $variant['uom_name'] ?? null,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_id' => $taxId,
                    'tax_rate' => $taxDef ? (float) ($taxDef['rate'] ?? 0) : 0.0,
                    'tax_breakdown' => $taxResult['breakdown'] === [] ? null : $taxResult['breakdown'],
                    'line_total' => $lineTotal,
                ]);
            }

            return $quote->load('items');
        });
    }
}
