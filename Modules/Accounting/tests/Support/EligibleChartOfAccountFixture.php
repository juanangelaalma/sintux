<?php

namespace Modules\Accounting\Tests\Support;

use Modules\Accounting\Models\ChartOfAccount;

final class EligibleChartOfAccountFixture
{
    /**
     * @return array{eligible: int, header: int, deleted: int}
     */
    public static function create(): array
    {
        $eligible = ChartOfAccount::query()->where('is_header', false)->firstOrFail();
        $header = ChartOfAccount::query()->where('is_header', true)->firstOrFail();
        $deleted = ChartOfAccount::query()
            ->where('is_header', false)
            ->whereKeyNot($eligible->id)
            ->firstOrFail();
        $deleted->delete();

        return [
            'eligible' => $eligible->id,
            'header' => $header->id,
            'deleted' => $deleted->id,
        ];
    }
}
