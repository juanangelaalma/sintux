<?php

namespace Modules\Purchasing\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Approval\Events\ApprovalRuleChanged;
use Modules\Approval\Events\TransactionApprovalFinalized;
use Modules\Purchasing\Listeners\FinalizePurchaseApproval;
use Modules\Purchasing\Listeners\ReevaluatePurchaseApprovals;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        TransactionApprovalFinalized::class => [
            FinalizePurchaseApproval::class,
        ],
        ApprovalRuleChanged::class => [
            ReevaluatePurchaseApprovals::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = true;

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
