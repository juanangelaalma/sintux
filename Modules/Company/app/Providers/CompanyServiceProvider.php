<?php

namespace Modules\Company\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Configuration\Middleware;
use Modules\Company\Http\Middleware\EnsureCompanyMember;
use Modules\Company\Http\Middleware\EnsurePermission;
use Modules\Company\Http\Middleware\ResolveTenant;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CompanyServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Company';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'company';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    // protected array $commands = [];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        AccessServiceProvider::class,
        EventServiceProvider::class,
        RouteServiceProvider::class,
        TenancyServiceProvider::class,
    ];

    /**
     * Configure the middleware owned by the Company module.
     */
    public static function configureMiddleware(Middleware $middleware): void
    {
        $middleware->alias([
            'company.member' => EnsureCompanyMember::class,
            'permission' => EnsurePermission::class,
        ]);

        $middleware->web(append: ResolveTenant::class);
    }

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
