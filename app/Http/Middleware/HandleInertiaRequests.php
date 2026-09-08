<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Modules\Company\Application\CompanyAccess;

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
        $branchScope = null;
        $activeBranch = null;
        $branches = collect();
        $isHq = false;
        $roles = [];
        $permissions = [];

        if (tenancy()->initialized && $request->user()) {
            $activeTenant = tenant();
            $tenantId = (string) tenant('id');

            $branches = collect(CompanyAccess::accessibleBranches($request->user(), $tenantId))
                ->sortBy('id')
                ->values();

            $isHq = CompanyAccess::isActiveBranchHq($request->user(), $tenantId);

            $accessibleBranchIds = CompanyAccess::accessibleBranchIds($request->user(), $tenantId);

            $branchScope = (string) session('branch_scope');

            if ($branchScope !== 'all' && $branchScope !== 'branch') {
                $branchScope = $isHq || count($accessibleBranchIds) > 1 ? 'all' : 'branch';
                session(['branch_scope' => $branchScope]);
            }

            if ($branchScope === 'branch') {
                $activeBranchId = (int) session('active_branch_id');

                if (! in_array($activeBranchId, $accessibleBranchIds, true)) {
                    $activeBranchId = $accessibleBranchIds[0] ?? 0;
                    session(['active_branch_id' => $activeBranchId]);
                }

                $activeBranch = $branches->firstWhere('id', $activeBranchId);
                $permissionBranchIds = $activeBranchId ? [$activeBranchId] : [];
            } else {
                $permissionBranchIds = $accessibleBranchIds;
            }

            $roles = CompanyAccess::rolesFor($request->user(), $tenantId);
            $permissions = CompanyAccess::permissionsFor($request->user(), $tenantId, $permissionBranchIds);
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'tenant' => $activeTenant,
                'branch_scope' => $branchScope,
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
