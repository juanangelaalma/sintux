<?php

namespace Modules\Product\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ProductServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Product';

    protected string $nameLower = 'product';

    protected array $providers = [
        RouteServiceProvider::class,
        EventServiceProvider::class,
    ];

    protected array $commands = [];
}
