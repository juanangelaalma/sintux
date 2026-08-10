<?php

namespace Modules\Company\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Company\Access\CompanyAccess;
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

                session()->forget(['active_tenant_id', 'active_branch_id', 'branch_scope']);
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
     * Ensure a branch scope context exists once tenancy is initialized.
     */
    private function resolveBranchSession(?Authenticatable $user, string $tenantId): void
    {
        /** @var User|null $user */
        if (! $user) {
            return;
        }

        $accessibleBranchIds = CompanyAccess::accessibleBranchIds($user, $tenantId);

        if ($accessibleBranchIds === []) {
            session()->forget(['active_branch_id', 'branch_scope']);

            return;
        }

        $scope = (string) session('branch_scope');
        $activeBranchId = (int) session('active_branch_id');

        if ($activeBranchId && in_array($activeBranchId, $accessibleBranchIds, true)) {
            $isHq = (bool) DB::table('branches')
                ->where('id', $activeBranchId)
                ->value('is_headquarters');

            if ($isHq) {
                session(['branch_scope' => 'all']);

                return;
            }

            if ($scope === 'all') {
                session(['branch_scope' => 'all']);

                return;
            }

            session(['branch_scope' => 'branch']);

            return;
        }

        $membership = $user->companyUserFor($tenantId);
        $userBranchId = $membership?->branch_id && in_array($membership->branch_id, $accessibleBranchIds, true)
            ? $membership->branch_id
            : $accessibleBranchIds[0];

        $isUserHq = (bool) DB::table('branches')
            ->where('id', $userBranchId)
            ->value('is_headquarters');

        if ($isUserHq) {
            session(['active_branch_id' => $userBranchId, 'branch_scope' => 'all']);

            return;
        }

        if ($scope !== 'branch' && count($accessibleBranchIds) > 1) {
            session(['branch_scope' => 'all']);
            session()->forget('active_branch_id');

            return;
        }

        session(['active_branch_id' => $userBranchId, 'branch_scope' => 'branch']);
    }
}
