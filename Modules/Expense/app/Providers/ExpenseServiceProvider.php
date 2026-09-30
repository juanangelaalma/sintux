<?php

namespace Modules\Expense\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class ExpenseServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Expense';

    protected string $nameLower = 'expense';

    protected array $providers = [
        RouteServiceProvider::class,
    ];

    protected array $commands = [];
}
