<?php

namespace Modules\Expense\Tests\Feature;

use Modules\Accounting\Application\TaxCalculator;
use Modules\Expense\Domain\Rules\CalculateExpenseTotals;
use PHPUnit\Framework\TestCase;

/**
 * Aturan perhitungan biaya. Setiap nama test memuat ID BR.
 *
 * Pure: tidak butuh tenant, database, atau auth.
 */
class ExpenseCalculationTest extends TestCase
{
    private CalculateExpenseTotals $totals;

    protected function setUp(): void
    {
        parent::setUp();

        $this->totals = new CalculateExpenseTotals(new TaxCalculator);
    }

    /**
     * BR-06: nominal 100.000, PPN 12% dengan pengali 11/12, toggle inklusif
     * aktif. DPP harus 90.090,0900 dan pajak 9.909,9100.
     *
     * Sumber PRD menyebut 90.090,090090 (6 desimal); kolom decimal(15,4)
     * memotongnya sesuai keputusan Q-06.
     */
    public function test_br_06_tax_inclusive_splits_base_and_tax_with_dpp_multiplier(): void
    {
        $result = $this->totals->execute(
            [['amount' => 100000, 'tax' => ['id' => 1, 'rate' => 12, 'dpp_multiplier' => true]]],
            [],
            true,
        );

        $this->assertEqualsWithDelta(90090.0900, $result['lines'][0]['amount_before_tax'], 0.0001);
        $this->assertEqualsWithDelta(9909.9100, $result['lines'][0]['tax_amount'], 0.0001);
        $this->assertEqualsWithDelta(9909.91, $result['tax_total'], 0.01);
        $this->assertEqualsWithDelta(100000.0, $result['grand_total'], 0.01);
    }

    /**
     * BR-06: mode eksklusif, nominal 100.000, PPN 11% biasa. DPP tetap
     * 100.000 dan pajak 11.000.
     */
    public function test_br_06_tax_exclusive_keeps_base_and_adds_tax(): void
    {
        $result = $this->totals->execute(
            [['amount' => 100000, 'tax' => ['id' => 1, 'rate' => 11]]],
            [],
            false,
        );

        $this->assertEqualsWithDelta(100000.0, $result['lines'][0]['amount_before_tax'], 0.0001);
        $this->assertEqualsWithDelta(11000.0, $result['lines'][0]['tax_amount'], 0.0001);
        $this->assertEqualsWithDelta(111000.0, $result['grand_total'], 0.01);
    }

    /**
     * BR-05: pemotongan 2 persen dihitung dari nilai sebelum pajak, bukan
     * dari nominal dan bukan dari total setelah pajak.
     *
     * Nominal 100.000 dengan PPN 11% menghasilkan DPP 100.000 dan total
     * 111.000. Pemotongan 2% dari DPP = 2.000; kalau salah dihitung dari
     * 111.000 hasilnya 2.220.
     */
    public function test_br_05_percent_withholding_is_calculated_from_amount_before_tax(): void
    {
        $result = $this->totals->execute(
            [['amount' => 100000, 'tax' => ['id' => 1, 'rate' => 11]]],
            ['type' => 'percent', 'value' => 2],
            false,
        );

        $this->assertEqualsWithDelta(2000.0, $result['withholding_total'], 0.0001);
        $this->assertNotEqualsWithDelta(2220.0, $result['withholding_total'], 0.01);
        $this->assertEqualsWithDelta(109000.0, $result['grand_total'], 0.01);
    }

    /**
     * BR-05: tipe nominal dipakai apa adanya, bukan persentase.
     */
    public function test_br_05_nominal_withholding_is_used_as_given(): void
    {
        $result = $this->totals->execute(
            [['amount' => 100000]],
            ['type' => 'nominal', 'value' => 2500.50],
            false,
        );

        $this->assertEqualsWithDelta(2500.50, $result['withholding_total'], 0.0001);
        $this->assertEqualsWithDelta(97500.0 - 0.5, $result['grand_total'], 0.01);
    }

    /**
     * BR-07: total = subtotal + pajak - pemotongan.
     */
    public function test_br_07_grand_total_is_subtotal_plus_tax_minus_withholding(): void
    {
        $result = $this->totals->execute(
            [
                ['amount' => 50000, 'tax' => ['id' => 1, 'rate' => 11]],
                ['amount' => 30000],
            ],
            ['type' => 'percent', 'value' => 10],
            false,
        );

        // subtotal = 80000 (DPP semua baris), pajak = 5500, withholding = 8000
        $this->assertEqualsWithDelta(80000.0, $result['subtotal'], 0.0001);
        $this->assertEqualsWithDelta(5500.0, $result['tax_total'], 0.0001);
        $this->assertEqualsWithDelta(8000.0, $result['withholding_total'], 0.0001);
        $this->assertEqualsWithDelta(77500.0, $result['grand_total'], 0.01);
    }

    /**
     * BR-03: satu transaksi boleh punya banyak baris akun, dan setiap
     * baris dihitung terpisah sebelum dijumlahkan.
     */
    public function test_br_03_multiple_lines_are_computed_per_line_then_summed(): void
    {
        $result = $this->totals->execute(
            [
                ['amount' => 100000, 'tax' => ['id' => 1, 'rate' => 11]],
                ['amount' => 200000, 'tax' => ['id' => 1, 'rate' => 11]],
                ['amount' => 70000],
            ],
            [],
            false,
        );

        $this->assertCount(3, $result['lines']);
        $this->assertEqualsWithDelta(11000.0, $result['lines'][0]['tax_amount'], 0.0001);
        $this->assertEqualsWithDelta(22000.0, $result['lines'][1]['tax_amount'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $result['lines'][2]['tax_amount'], 0.0001);
        $this->assertEqualsWithDelta(33000.0, $result['tax_total'], 0.0001);
        $this->assertEqualsWithDelta(370000.0, $result['subtotal'], 0.0001);
        $this->assertEqualsWithDelta(403000.0, $result['grand_total'], 0.01);
    }

    /**
     * Baris tanpa pajak tidak menambah pajak apa pun, dan breakdown-nya kosong.
     */
    public function test_line_without_tax_produces_no_tax_and_empty_breakdown(): void
    {
        $result = $this->totals->execute([['amount' => 25000, 'tax' => null]], [], false);

        $this->assertEqualsWithDelta(0.0, $result['lines'][0]['tax_amount'], 0.0001);
        $this->assertSame([], $result['lines'][0]['tax_breakdown']);
        $this->assertEqualsWithDelta(25000.0, $result['grand_total'], 0.01);
    }

    /**
     * Tanpa pemotongan, withholding_total nol dan total = subtotal + pajak.
     */
    public function test_no_withholding_yields_zero_and_grand_total_of_subtotal_plus_tax(): void
    {
        $result = $this->totals->execute(
            [['amount' => 40000, 'tax' => ['id' => 1, 'rate' => 10]]],
            [],
            false,
        );

        $this->assertEqualsWithDelta(0.0, $result['withholding_total'], 0.0001);
        $this->assertEqualsWithDelta(44000.0, $result['grand_total'], 0.01);
    }

    /**
     * Percent dihitung dari DPP gabungan seluruh baris, bukan per baris.
     * Ini yang membuat pemotongan tidak terpengaruh pembulatan per baris.
     */
    public function test_percent_withholding_uses_combined_subtotal_not_per_line_values(): void
    {
        $result = $this->totals->execute(
            [
                ['amount' => 33333, 'tax' => ['id' => 1, 'rate' => 11]],
                ['amount' => 33333],
            ],
            ['type' => 'percent', 'value' => 1],
            false,
        );

        // DPP gabungan = 66666; 1% dari 66666 = 666.66
        $this->assertEqualsWithDelta(66666.0, $result['subtotal'], 0.0001);
        $this->assertEqualsWithDelta(666.66, $result['withholding_total'], 0.0001);
    }

    /**
     * Nominal nol menghasilkan semua total nol, supaya jurnal tidak pernah
     * dibuat dari baris kosong.
     */
    public function test_zero_amount_yields_zero_totals(): void
    {
        $result = $this->totals->execute(
            [['amount' => 0, 'tax' => ['id' => 1, 'rate' => 11]]],
            ['type' => 'percent', 'value' => 10],
            false,
        );

        $this->assertEqualsWithDelta(0.0, $result['subtotal'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $result['tax_total'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $result['withholding_total'], 0.0001);
        $this->assertEqualsWithDelta(0.0, $result['grand_total'], 0.0001);
    }
}
