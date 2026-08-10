<?php

namespace App\Http\Responses;

use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Modules\Company\Models\CompanyUser;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $user = auth()->user();

        if ($user->role === 'superadmin') {
            return redirect()->route('admin.dashboard');
        }

        // Cari tenant default
        /** @var CompanyUser|null $defaultTenant */
        $defaultTenant = $user->defaultCompany();

        if ($defaultTenant) {
            session(['active_tenant_id' => $defaultTenant->tenant_id]);

            if ($defaultTenant->branch_id) {
                session(['active_branch_id' => $defaultTenant->branch_id]);
            }
        }

        return redirect()->intended(config('fortify.home'));
    }
}
