<?php

namespace Modules\Accounting\Application;

use Modules\Accounting\Models\ChartOfAccount;

class ChartOfAccountQuery
{
    /**
     * Return list of all chart of accounts.
     *
     * @return list<array<string, mixed>>
     */
    public function listChartOfAccounts(): array
    {
        return ChartOfAccount::select('id', 'code', 'name')
            ->where('is_header', false)
            ->get()
            ->map(fn (ChartOfAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ])
            ->values()
            ->all();
    }
}
