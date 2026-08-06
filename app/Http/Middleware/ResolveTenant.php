<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && $request->user()->role === 'superadmin') {
            return $next($request);
        }

        $tenantId = session('active_tenant_id');

        if (! $tenantId && auth()->check()) {
            $defaultTenant = auth()->user()->defaultCompany();

            if ($defaultTenant) {
                $tenantId = $defaultTenant->tenant_id;
                session(['active_tenant_id' => $tenantId]);
            }
        }

        if ($tenantId) {
            try {
                tenancy()->initialize($tenantId);
                $this->resolveBranchSession($request->user(), $tenantId);
            } catch (\Exception $e) {
                if (tenancy()->initialized) {
                    tenancy()->end();
                }

                session()->forget(['active_tenant_id', 'active_branch_id']);
            }
        }

        try {
            return $next($request);
        } finally {
            if (tenancy()->initialized) {
                tenancy()->end();
            }
        }
    }

    /**
     * Ensure an active branch context exists once tenancy is initialized.
     */
    private function resolveBranchSession(?Authenticatable $user, string $tenantId): void
    {
        if (session('active_branch_id')) {
            return;
        }

        /** @var User|null $user */
        $membership = $user?->companyUserFor($tenantId);

        $branchId = $membership?->branch_id;

        if (! $branchId) {
            $branchId = DB::table('branches')
                ->where('is_active', true)
                ->orderByDesc('is_headquarters')
                ->value('id');
        }

        if ($branchId) {
            session(['active_branch_id' => (int) $branchId]);
        }
    }
}
