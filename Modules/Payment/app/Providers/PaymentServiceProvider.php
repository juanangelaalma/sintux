<?php

namespace Modules\Payment\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class PaymentServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Payment';

    protected string $nameLower = 'payment';

    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
