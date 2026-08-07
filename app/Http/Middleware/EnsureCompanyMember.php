<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
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

        if (! session('active_tenant_id')) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
