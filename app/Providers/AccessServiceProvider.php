<?php

namespace App\Providers;

use App\Access\CompanyAccess;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
        });

        if (! Schema::hasTable('permissions')) {
            return;
        }

        foreach (Permission::pluck('slug') as $slug) {
            Gate::define($slug, function (User $user) use ($slug) {
                return CompanyAccess::can(
                    $user,
                    session('active_tenant_id'),
                    session('active_branch_id'),
                    $slug,
                );
            });
        }
    }
}
