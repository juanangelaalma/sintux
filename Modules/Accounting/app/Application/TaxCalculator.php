<?php

namespace Modules\Accounting\Application;

/**
 * Kalkulator pajak tunggal untuk seluruh dokumen.
 *
 * Pure function: tanpa DB, tanpa auth. Input = basis kena pajak per baris
 * + definisi tax persis seperti proyeksi TaxQuery. Output = breakdown per
 * anggota + total. Backend adalah satu-satunya sumber angka; mirror TS di
 * FE hanya untuk preview.
 *
 * Aturan:
 * - Pengali 11/12 (DPP Nilai Lain): DPP = basis x 11/12 x rate%.
 * - Majemuk: basis anggota compound = net + jumlah pajak anggota
 *   sebelumnya (intermediate tanpa pembulatan).
 * - Pembulatan: tiap anggota dibulatkan 2 desimal half-up; total adalah
 *   jumlah nilai yang sudah dibulatkan.
 * - Grup beranggota withholding didukung di level kalkulator (signed rate
 *   negatif), walau TaxQuery menyembunyikannya dari transaksi sampai R2.
 * - Inklusif: net dipecah dari gross via pembagi linier
 *   (1 + jumlah koefisien efektif), lalu dihitung jalur eksklusif.
 */
final class TaxCalculator
{
    private const DPP_FACTOR = 11.0 / 12.0;

    /**
     * @param  array{id: int, rate: float|int|string, type?: string, dpp_multiplier?: bool, members?: list<array{id: int, signed_rate?: float|int|string, rate?: float|int|string, is_compound?: bool, dpp_multiplier?: bool}>}|null  $taxDef
     * @return array{breakdown: list<array{tax_id: int, rate: float, amount: float}>, total: float}
     */
    public function calculate(float $taxable, ?array $taxDef, bool $isTaxInclusive = false): array
    {
        if ($taxDef === null || $taxable <= 0) {
            return ['breakdown' => [], 'total' => 0.0];
        }

        $members = $this->normalize($taxDef);

        if ($members === []) {
            return ['breakdown' => [], 'total' => 0.0];
        }

        $net = $isTaxInclusive ? $this->extractNet($taxable, $members) : $taxable;

        return $this->calculateExclusive($net, $members);
    }

    /**
     * @param  array{id: int, rate: float|int|string, type?: string, dpp_multiplier?: bool, members?: list<array<string, mixed>>}  $taxDef
     * @return list<array{id: int, signed_rate: float, coefficient: float, is_compound: bool, dpp_multiplier: bool}>
     */
    private function normalize(array $taxDef): array
    {
        if (($taxDef['type'] ?? 'single') === 'group') {
            $members = [];

            foreach ($taxDef['members'] ?? [] as $member) {
                $signedRate = (float) ($member['signed_rate'] ?? $member['rate'] ?? 0);
                $dppMultiplier = (bool) ($member['dpp_multiplier'] ?? false);
                $coefficient = $this->coefficient($signedRate, $dppMultiplier);

                $members[] = [
                    'id' => (int) $member['id'],
                    'signed_rate' => $signedRate,
                    'coefficient' => $coefficient,
                    'is_compound' => (bool) ($member['is_compound'] ?? false),
                    'dpp_multiplier' => $dppMultiplier,
                ];
            }

            return $members;
        }

        $rate = abs((float) ($taxDef['rate'] ?? 0));
        $dppMultiplier = (bool) ($taxDef['dpp_multiplier'] ?? false);

        return [[
            'id' => (int) $taxDef['id'],
            'signed_rate' => $rate,
            'coefficient' => $this->coefficient($rate, $dppMultiplier),
            'is_compound' => false,
            'dpp_multiplier' => $dppMultiplier,
        ]];
    }

    private function coefficient(float $signedRate, bool $dppMultiplier): float
    {
        $factor = $dppMultiplier ? self::DPP_FACTOR : 1.0;

        return $factor * ($signedRate / 100);
    }

    /**
     * Pecah gross inklusif menjadi net via pembagi linier.
     *
     * @param  list<array{coefficient: float, is_compound: bool}>  $members
     */
    private function extractNet(float $gross, array $members): float
    {
        $divisor = 1.0;
        $accumulated = 0.0;

        foreach ($members as $member) {
            $effective = $member['is_compound']
                ? $member['coefficient'] * (1.0 + $accumulated)
                : $member['coefficient'];

            $accumulated += $effective;
        }

        $divisor += $accumulated;

        return $divisor > 0 ? $gross / $divisor : $gross;
    }

    /**
     * @param  list<array{id: int, signed_rate: float, coefficient: float, is_compound: bool, dpp_multiplier: bool}>  $members
     * @return array{breakdown: list<array{tax_id: int, rate: float, amount: float}>, total: float}
     */
    private function calculateExclusive(float $net, array $members): array
    {
        $breakdown = [];
        $rawAccumulated = 0.0;
        $total = 0.0;

        foreach ($members as $member) {
            $basis = $member['is_compound'] ? $net + $rawAccumulated : $net;
            $raw = $basis * $member['coefficient'];
            $rawAccumulated += $raw;

            $amount = round($raw, 2, PHP_ROUND_HALF_UP);
            $total += $amount;

            $breakdown[] = [
                'tax_id' => $member['id'],
                'rate' => $member['signed_rate'],
                'amount' => $amount,
            ];
        }

        return ['breakdown' => $breakdown, 'total' => $total];
    }
}
