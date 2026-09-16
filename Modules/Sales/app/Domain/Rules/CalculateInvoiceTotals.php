<?php

namespace Modules\Sales\Domain\Rules;

use Modules\Accounting\Application\TaxCalculator;

/**
 * Kalkulasi total faktur penjualan: diskon per baris (%|nominal),
 * diskon invoice (%|nominal) dari total setelah diskon per baris,
 * lalu pajak per baris dari DPP (net − alokasi diskon invoice).
 *
 * Diskon tetap milik dokumen ini; bagian PAJAK didelegasikan ke
 * Accounting\Application\TaxCalculator (pengali 11/12, grup, majemuk,
 * pembulatan 2dp half-up per anggota). Tanpa DB, tanpa auth.
 */
final class CalculateInvoiceTotals
{
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
    ) {}

    /**
     * @param  list<array{qty: int|float, unit_price: int|float, discount_type?: string|null, discount_value?: int|float|null, tax_rate?: int|float, tax?: array{id: int, rate: float|int|string, type?: string, dpp_multiplier?: bool, members?: list<array<string, mixed>>}|null}>  $lines
     * @param  array{type?: string|null, value?: int|float|null}  $invoiceDiscount
     * @return array{
     *     lines: list<array{line_gross: float, discount_amount: float, line_net: float, allocated_invoice_discount: float, taxable_amount: float, tax_amount: float, tax_breakdown: list<array{tax_id: int, rate: float, amount: float}>, line_total: float}>,
     *     subtotal: float,
     *     line_discount_total: float,
     *     net_after_line_discount: float,
     *     invoice_discount_amount: float,
     *     tax_amount: float,
     *     total: float
     * }
     */
    public function execute(array $lines, array $invoiceDiscount = [], bool $isTaxInclusive = false): array
    {
        $computed = [];
        $subtotal = 0.0;
        $lineDiscountTotal = 0.0;

        foreach ($lines as $line) {
            $qty = (float) $line['qty'];
            $gross = $qty * (float) $line['unit_price'];
            $discount = self::discountAmount($gross, $line['discount_type'] ?? null, $line['discount_value'] ?? null);
            $net = $gross - $discount;

            $computed[] = [
                'line_gross' => $gross,
                'discount_amount' => $discount,
                'line_net' => $net,
                'tax' => $line['tax'] ?? null,
                'allocated_invoice_discount' => 0.0,
                'taxable_amount' => $net,
                'tax_amount' => 0.0,
                'tax_breakdown' => [],
                'line_total' => $net,
            ];

            $subtotal += $gross;
            $lineDiscountTotal += $discount;
        }

        $netAfterLineDiscount = $subtotal - $lineDiscountTotal;
        $invoiceDiscountAmount = self::discountAmount(
            $netAfterLineDiscount,
            $invoiceDiscount['type'] ?? null,
            $invoiceDiscount['value'] ?? null
        );

        $taxTotal = 0.0;
        $total = 0.0;

        foreach ($computed as $i => $line) {
            $allocated = $netAfterLineDiscount > 0
                ? $invoiceDiscountAmount * ($line['line_net'] / $netAfterLineDiscount)
                : 0.0;
            $taxable = $line['line_net'] - $allocated;

            $taxResult = $this->taxCalculator->calculate($taxable, $line['tax'], $isTaxInclusive);
            $tax = $taxResult['total'];
            $lineTotal = $isTaxInclusive ? $taxable : $taxable + $tax;

            $computed[$i]['allocated_invoice_discount'] = $allocated;
            $computed[$i]['taxable_amount'] = $taxable;
            $computed[$i]['tax_amount'] = $tax;
            $computed[$i]['tax_breakdown'] = $taxResult['breakdown'];
            $computed[$i]['line_total'] = $lineTotal;
            unset($computed[$i]['tax']);

            $taxTotal += $tax;
            $total += $lineTotal;
        }

        return [
            'lines' => $computed,
            'subtotal' => $subtotal,
            'line_discount_total' => $lineDiscountTotal,
            'net_after_line_discount' => $netAfterLineDiscount,
            'invoice_discount_amount' => $invoiceDiscountAmount,
            'tax_amount' => $taxTotal,
            'total' => $total,
        ];
    }

    private static function discountAmount(float $basis, ?string $type, mixed $value): float
    {
        if ($type === null || $type === '' || $basis <= 0) {
            return 0.0;
        }

        if ($type === 'percent') {
            return $basis * ((float) $value / 100);
        }

        return min((float) $value, $basis);
    }
}
