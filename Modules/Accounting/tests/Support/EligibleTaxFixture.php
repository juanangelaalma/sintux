<?php

namespace Modules\Accounting\Tests\Support;

use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Tax;

final class EligibleTaxFixture
{
    /**
     * @return array{0: int, 1: int}
     */
    public static function create(string $suffix): array
    {
        $accountId = ChartOfAccount::query()->value('id');

        return [
            Tax::query()->create([
                'code' => 'PURCHASE-'.$suffix,
                'name' => 'Purchase tax',
                'rate' => '11.0000',
                'input_account_id' => $accountId,
                'is_active' => true,
            ])->id,
            Tax::query()->create([
                'code' => 'SALES-'.$suffix,
                'name' => 'Sales tax',
                'rate' => '12.0000',
                'output_account_id' => $accountId,
                'is_active' => true,
            ])->id,
        ];
    }
}
