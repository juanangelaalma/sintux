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

        if (! CompanyAccess::isActiveBranchHq($user, $tenantId)) {
            abort(403, 'Fitur ini hanya tersedia untuk Head Office.');
        }

        return $next($request);
    }
}
