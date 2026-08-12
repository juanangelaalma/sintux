<?php

namespace Modules\Company\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Company\Application\CompanyAccess;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyMember
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->role === 'superadmin') {
            return redirect()->route('admin.dashboard');
        }

        $tenantId = session('active_tenant_id');

        if (! $tenantId) {
            return redirect()->route('dashboard');
        }

        if (! $user || ! CompanyAccess::hasMembership($user, (string) $tenantId)) {
            abort(403);
        }

        return $next($request);
    }
}
