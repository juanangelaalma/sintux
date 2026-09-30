<?php

namespace Modules\Expense\Domain\Rules;

use Modules\Accounting\Application\TaxCalculator;

/**
 * Kalkulasi total biaya (BR-05, BR-06, BR-07).
 *
 * Pure: tanpa DB, tanpa auth. Dua tahap.
 *
 * 1. Per baris: nominal `amount` apa adanya seperti diinput user. Kalau
 *    toggle "Harga termasuk pajak" aktif, DPP dipecah dari nominal lewat
 *    TaxCalculator (BR-06). Kalau tidak, DPP = nominal. Pajak selalu
 *    dihitung oleh TaxCalculator, jadi pengali 11/12, grup, dan majemuk
 *    ditangani di satu tempat.
 *
 * 2. Pemotongan (BR-05): persen dihitung dari `subtotal` yang adalah
 *    jumlah DPP semua baris — bukan dari nominal dan bukan dari total
 *    setelah pajak. Tipe nominal dipakai apa adanya.
 *
 * Total akhir (BR-07) = subtotal + pajak - pemotongan.
 */
final class CalculateExpenseTotals
{
    /**
     * Pembulatan internal mengikuti kolom DB: 4 desimal. Jurnal tetap 2
     * desimal dan balance dijamin PostExpenseJournal. Lihat
     * docs/expense/OPEN_QUESTIONS.md Q-06.
     */
    private const PRECISION = 4;

    public function __construct(
        private readonly TaxCalculator $taxCalculator,
    ) {}

    /**
     * @param  list<array{amount: int|float, tax?: array{id: int, rate: float|int|string, type?: string, dpp_multiplier?: bool, members?: list<array<string, mixed>>}|null}>  $lines
     * @param  array{type?: string|null, value?: int|float|null}|null  $withholding
     * @return array{
     *     lines: list<array{amount: float, amount_before_tax: float, tax_amount: float, tax_breakdown: list<array{tax_id: int, rate: float, amount: float}>}>,
     *     subtotal: float,
     *     tax_total: float,
     *     withholding_total: float,
     *     grand_total: float
     * }
     */
    public function execute(array $lines, ?array $withholding = null, bool $isTaxInclusive = false): array
    {
        $computed = [];
        $subtotal = 0.0;
        $taxTotal = 0.0;

        foreach ($lines as $line) {
            $amount = self::round((float) $line['amount']);
            $taxDef = $line['tax'] ?? null;

            // TaxCalculator mengembalikan total pajak dan breakdown-nya.
            // Untuk mode inklusif, nominal yang diinput sudah mengandung
            // pajak, jadi DPP = nominal - total pajak (BR-06).
            $taxResult = $this->taxCalculator->calculate($amount, $taxDef, $isTaxInclusive);
            $taxAmount = self::round($taxResult['total']);
            $amountBeforeTax = $isTaxInclusive
                ? self::round($amount - $taxAmount)
                : $amount;

            $computed[] = [
                'amount' => $amount,
                'amount_before_tax' => $amountBeforeTax,
                'tax_amount' => $taxAmount,
                'tax_breakdown' => $taxResult['breakdown'],
            ];

            $subtotal += $amountBeforeTax;
            $taxTotal += $taxAmount;
        }

        $subtotal = self::round($subtotal);
        $taxTotal = self::round($taxTotal);

        $withholdingTotal = self::withholdingTotal($subtotal, $withholding);
        $grandTotal = self::round($subtotal + $taxTotal - $withholdingTotal);

        return [
            'lines' => $computed,
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'withholding_total' => $withholdingTotal,
            'grand_total' => $grandTotal,
        ];
    }

    /**
     * BR-05: persen dihitung dari nilai sebelum pajak.
     *
     * @param  array{type?: string|null, value?: int|float|null}|null  $withholding
     */
    public static function withholdingTotal(float $subtotal, ?array $withholding = null): float
    {
        $type = $withholding['type'] ?? null;

        if ($type === null || $type === '') {
            return 0.0;
        }

        $value = (float) ($withholding['value'] ?? 0);

        if ($type === 'percent') {
            return self::round($subtotal * ($value / 100));
        }

        return self::round($value);
    }

    private static function round(float $value): float
    {
        return round($value, self::PRECISION, PHP_ROUND_HALF_UP);
    }
}
