<?php

namespace Modules\Warehouse\Providers;

use Modules\Company\Models\Branch;
use Modules\Warehouse\Observers\BranchWarehouseObserver;
use Nwidart\Modules\Support\ModuleServiceProvider;

class WarehouseServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Warehouse';

    protected string $nameLower = 'warehouse';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        Branch::observe(BranchWarehouseObserver::class);
    }
}
