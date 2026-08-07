<?php

namespace App\Http\Middleware;

use App\Models\CompanyUser;
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
        /** @var User|null $user */
        $membership = $user?->companyUserFor($tenantId);

        $activeBranchId = (int) session('active_branch_id');

        if ($activeBranchId && $this->branchIsAccessible($membership, $activeBranchId)) {
            return;
        }

        $branchId = $membership?->branch_id;

        if ($branchId && $this->branchIsAccessible($membership, $branchId)) {
            session(['active_branch_id' => $branchId]);

            return;
        }

        $branchId = DB::table('branches')
            ->where('is_active', true)
            ->orderByDesc('is_headquarters')
            ->value('id');

        if ($branchId) {
            session(['active_branch_id' => (int) $branchId]);
        }
    }

    /**
     * Determine whether a membership may use the given branch.
     */
    private function branchIsAccessible(?CompanyUser $membership, int $branchId): bool
    {
        if (! $membership) {
            return false;
        }

        $isHq = false;
        if ($membership->branch_id) {
            $isHq = (bool) DB::table('branches')
                ->where('id', $membership->branch_id)
                ->where('is_active', true)
                ->value('is_headquarters');
        }

        if ($isHq) {
            return DB::table('branches')
                ->where('is_active', true)
                ->where('id', $branchId)
                ->exists();
        }

        return in_array($branchId, $membership->allowedBranchIds(), true);
    }
}
