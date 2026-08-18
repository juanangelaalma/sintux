<?php

namespace Modules\Purchasing\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class PurchasingServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Purchasing';

    protected string $nameLower = 'purchasing';

    protected array $providers = [
        RouteServiceProvider::class,
        EventServiceProvider::class,
    ];

    protected array $commands = [];
}
