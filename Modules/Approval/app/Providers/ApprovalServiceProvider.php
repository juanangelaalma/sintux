<?php

namespace Modules\Approval\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ApprovalServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Approval';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'approval';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
