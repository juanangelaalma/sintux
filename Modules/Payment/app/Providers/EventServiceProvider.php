<?php

namespace Modules\Payment\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Approval\Events\TransactionApprovalFinalized;
use Modules\Payment\Listeners\FinalizePurchasePaymentApproval;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        TransactionApprovalFinalized::class => [
            FinalizePurchasePaymentApproval::class,
        ],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
