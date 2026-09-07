<?php

namespace Modules\Purchasing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Company\Application\CompanyAccess;
use Symfony\Component\HttpFoundation\Response;

class EnsureHeadquarters
{
    /**
     * Ensure the current user is operating from the headquarters branch.
     * Non-HQ branches are blocked from accessing HQ-only purchasing features.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $tenantId = (string) session('active_tenant_id');

        if (! $user || ! $tenantId) {
            abort(403);
        }

        $branches = CompanyAccess::accessibleBranches($user, $tenantId);
        $activeBranchId = (int) session('active_branch_id');

        // Find the active branch in the accessible branches list
        $activeBranch = collect($branches)->firstWhere('id', $activeBranchId);

        // If no active branch set, check if user has only one branch that is HQ
        if (! $activeBranch) {
            $isHq = collect($branches)->contains('is_headquarters', true);
            if (! $isHq) {
                abort(403, 'Fitur ini hanya tersedia untuk Head Office.');
            }

            return $next($request);
        }

        if (! $activeBranch->is_headquarters) {
            abort(403, 'Fitur ini hanya tersedia untuk Head Office.');
        }

        return $next($request);
    }
}
