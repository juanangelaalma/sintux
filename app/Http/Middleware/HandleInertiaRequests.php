<?php

namespace App\Http\Middleware;

use App\Access\CompanyAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $activeTenant = null;
        $activeBranch = null;
        $branches = collect();
        $isHq = false;
        $roles = [];
        $permissions = [];

        if (tenancy()->initialized && $request->user()) {
            $activeTenant = tenant();

            $membership = $request->user()->companyUserFor((string) tenant('id'));

            if ($membership && $membership->branch_id) {
                $isHq = (bool) DB::table('branches')
                    ->where('id', $membership->branch_id)
                    ->value('is_headquarters');
            }

            $branches = DB::table('branches')
                ->where('is_active', true)
                ->get();

            if (! $isHq && $membership) {
                $allowedBranchIds = $membership->allowedBranchIds();
                $branches = $branches->whereIn('id', $allowedBranchIds);
            }

            $activeBranchId = (int) session('active_branch_id');

            if (! $branches->contains('id', $activeBranchId)) {
                $activeBranchId = (int) ($membership?->branch_id ?? $branches->first()?->id ?? 0);

                if ($activeBranchId) {
                    session(['active_branch_id' => $activeBranchId]);
                }
            }

            $activeBranch = $branches->firstWhere('id', $activeBranchId);

            $roles = CompanyAccess::rolesFor($request->user(), (string) tenant('id'));
            $permissions = CompanyAccess::permissionsFor($request->user(), (string) tenant('id'), $activeBranchId ?: null);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'tenant' => $activeTenant,
                'branch' => $activeBranch,
                'branches' => $branches->values(),
                'is_hq' => $isHq,
                'roles' => $roles,
                'permissions' => $permissions,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
