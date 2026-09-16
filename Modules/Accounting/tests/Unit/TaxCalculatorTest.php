<?php

namespace Modules\Accounting\Tests\Unit;

use Modules\Accounting\Application\TaxCalculator;
use PHPUnit\Framework\TestCase;

class TaxCalculatorTest extends TestCase
{
    private TaxCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calc = new TaxCalculator;
    }

    public function test_single_without_multiplier(): void
    {
        $result = $this->calc->calculate(100000, $this->single(1, 12));

        $this->assertSame([['tax_id' => 1, 'rate' => 12.0, 'amount' => 12000.0]], $result['breakdown']);
        $this->assertEquals(12000.0, $result['total']);
    }

    public function test_single_with_multiplier_uses_11_over_12(): void
    {
        $result = $this->calc->calculate(100000, $this->single(1, 12, true));

        $this->assertEquals(11000.0, $result['total']);
        $this->assertEquals(11000.0, $result['breakdown'][0]['amount']);
    }

    public function test_group_non_compound_with_withholding_member(): void
    {
        // PPN12 (multiplier) + PPh23 (-2%) non-majemuk → 11.000 − 2.000.
        $group = [
            'id' => 9,
            'type' => 'group',
            'members' => [
                ['id' => 1, 'signed_rate' => 12.0, 'dpp_multiplier' => true, 'is_compound' => false],
                ['id' => 2, 'signed_rate' => -2.0, 'dpp_multiplier' => false, 'is_compound' => false],
            ],
        ];

        $result = $this->calc->calculate(100000, $group);

        $this->assertEquals(11000.0, $result['breakdown'][0]['amount']);
        $this->assertEquals(-2000.0, $result['breakdown'][1]['amount']);
        $this->assertEquals(9000.0, $result['total']);
    }

    public function test_group_compound_versus_non_compound(): void
    {
        $members = fn (bool $compound): array => [
            'id' => 9,
            'type' => 'group',
            'members' => [
                ['id' => 1, 'signed_rate' => 5.0, 'is_compound' => false],
                ['id' => 2, 'signed_rate' => 10.0, 'is_compound' => $compound],
            ],
        ];

        $plain = $this->calc->calculate(100000, $members(false));
        $this->assertEquals(5000.0, $plain['breakdown'][0]['amount']);
        $this->assertEquals(10000.0, $plain['breakdown'][1]['amount']);
        $this->assertEquals(15000.0, $plain['total']);

        $compound = $this->calc->calculate(100000, $members(true));
        $this->assertEquals(5000.0, $compound['breakdown'][0]['amount']);
        $this->assertEquals(10500.0, $compound['breakdown'][1]['amount']);
        $this->assertEquals(15500.0, $compound['total']);
    }

    public function test_rounding_half_up_per_member(): void
    {
        // 33.333 × 11/12 × 12% = 3.66663 → 3.67.
        $result = $this->calc->calculate(33.333, $this->single(1, 12, true));

        $this->assertEquals(3.67, $result['breakdown'][0]['amount']);
        $this->assertEquals(3.67, $result['total']);

        $scaled = $this->calc->calculate(33333, $this->single(1, 12, true));

        $this->assertEquals(3666.63, $scaled['breakdown'][0]['amount']);
    }

    public function test_inclusive_single_extracts_net(): void
    {
        // Gross 112.000 inklusif PPN 12% → net 100.000, pajak 12.000.
        $result = $this->calc->calculate(112000, $this->single(1, 12), true);

        $this->assertEquals(12000.0, $result['total']);
    }

    public function test_inclusive_with_multiplier_extracts_net(): void
    {
        // Gross 111.000 inklusif PPN 12% + pengali → net 100.000, pajak 11.000.
        $result = $this->calc->calculate(111000, $this->single(1, 12, true), true);

        $this->assertEquals(11000.0, $result['total']);
    }

    public function test_null_tax_returns_zero(): void
    {
        $result = $this->calc->calculate(100000, null);

        $this->assertSame([], $result['breakdown']);
        $this->assertEquals(0.0, $result['total']);
    }

    /**
     * @return array{id: int, rate: float, type: string, dpp_multiplier: bool}
     */
    private function single(int $id, float $rate, bool $multiplier = false): array
    {
        return ['id' => $id, 'rate' => $rate, 'type' => 'single', 'dpp_multiplier' => $multiplier];
    }
}
