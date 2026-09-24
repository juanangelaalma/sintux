<?php

namespace Modules\Accounting\Application\Journal;

use Modules\Accounting\Models\ChartOfAccount;

class DefaultPayableAccount
{
    public function execute(): ChartOfAccount
    {
        $account = ChartOfAccount::query()
            ->where('seed_key', 'accounting.coa.2101')
            ->where('is_header', false)
            ->whereNull('deleted_at')
            ->first();

        if (! $account) {
            throw new \LogicException('Akun hutang default (2101 Utang Usaha) tidak ditemukan.');
        }

        return $account;
    }
}
