<?php

namespace Modules\Purchasing\Providers;

use Modules\Purchasing\Infrastructure\External\HttpSupplierDoClient;
use Modules\Purchasing\Infrastructure\External\SupplierDoClient;
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

    public function register(): void
    {
        parent::register();

        $this->app->bind(SupplierDoClient::class, HttpSupplierDoClient::class);
    }
}
