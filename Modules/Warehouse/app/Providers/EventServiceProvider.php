<?php

namespace Modules\Warehouse\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Approval\Events\ApprovalRuleChanged;
use Modules\Approval\Events\TransactionApprovalFinalized;
use Modules\Warehouse\Listeners\FinalizeStockTransferApproval;
use Modules\Warehouse\Listeners\ReevaluateStockTransferApprovals;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        TransactionApprovalFinalized::class => [
            FinalizeStockTransferApproval::class,
        ],
        ApprovalRuleChanged::class => [
            ReevaluateStockTransferApprovals::class,
        ],
    ];

    protected static $shouldDiscoverEvents = true;

    protected function configureEmailVerification(): void {}
}
