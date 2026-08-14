<?php

namespace Modules\Company\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Modules\Company\Application\CompanyAccess;

class AccessServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability) {
            if ($user->role === 'superadmin') {
                return true;
            }

            $activeTenantId = session('active_tenant_id');

            if ($activeTenantId && CompanyAccess::can($user, (string) $activeTenantId, $ability)) {
                return true;
            }
        });
    }
}
