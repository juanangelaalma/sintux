<?php

namespace Modules\Company\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Modules\Company\Application\CompanyAccess;
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
            $defaultTenant = CompanyAccess::defaultMembership(auth()->user());

            if ($defaultTenant) {
                $tenantId = $defaultTenant['tenant_id'];
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
     *
     * The active branch drives the data scope. HQ users default to their
     * headquarters branch; "all" scope is only kept when explicitly chosen.
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

        /*
         * Keep a valid explicit selection, whatever the branch.
         */
        if ($activeBranchId && in_array($activeBranchId, $accessibleBranchIds, true)) {
            if ($scope !== 'all') {
                session(['branch_scope' => 'branch']);
            }

            return;
        }

        /*
         * Explicit "all" scope without an active branch.
         */
        if ($scope === 'all') {
            session()->forget('active_branch_id');

            return;
        }

        /*
         * Default: the membership branch, or the first accessible branch
         * (headquarters first for multi-branch memberships).
         */
        $membershipBranchId = CompanyAccess::membershipBranchId($user, $tenantId);

        $defaultBranchId = $membershipBranchId && in_array($membershipBranchId, $accessibleBranchIds, true)
            ? $membershipBranchId
            : $accessibleBranchIds[0];

        session(['active_branch_id' => $defaultBranchId, 'branch_scope' => 'branch']);
    }
}
