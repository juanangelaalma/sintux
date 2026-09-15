<?php

namespace Modules\Sales\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class SalesServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Sales';

    protected string $nameLower = 'sales';

    protected array $providers = [
        RouteServiceProvider::class,
        EventServiceProvider::class,
    ];

    protected array $commands = [];
}
