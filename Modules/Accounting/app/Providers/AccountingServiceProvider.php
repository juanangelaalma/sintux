<?php

namespace Modules\Accounting\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class AccountingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Accounting';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'accounting';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        RouteServiceProvider::class,
    ];
}
