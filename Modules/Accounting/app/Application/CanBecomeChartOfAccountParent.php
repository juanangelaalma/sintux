<?php

namespace Modules\Accounting\Application;

use Modules\Accounting\Models\ChartOfAccount;

class CanBecomeChartOfAccountParent
{
    public function execute(ChartOfAccount $account): bool
    {
        // Replace this once posted journal transactions are owned by Accounting.
        return true;
    }
}
