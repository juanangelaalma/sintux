<?php

namespace Modules\Company\Application;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Company\Models\Permission;
use Modules\Company\Models\Role;

class CompanyAccess
{
    /**
     * Branch ids the user may view data for within a tenant, cached per request.
     *
     * @var array<string, list<int>>
     */
    private static array $accessibleBranchIds = [];

    /**
     * Resolve the default membership values needed to initialize tenant context.
     *
     * @return array{tenant_id: string, branch_id: int|null}|null
     */
    public static function defaultMembership(User $user): ?array
    {
        $membership = CompanyUser::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->first();

        if (! $membership) {
            return null;
        }

        return [
            'tenant_id' => (string) $membership->tenant_id,
            'branch_id' => $membership->branch_id ? (int) $membership->branch_id : null,
        ];
    }

    public static function hasMembership(User $user, string $tenantId): bool
    {
        return self::membershipFor($user, $tenantId) !== null;
    }

    public static function membershipBranchId(User $user, string $tenantId): ?int
    {
        $branchId = self::membershipFor($user, $tenantId)?->branch_id;

        return $branchId ? (int) $branchId : null;
    }

    /**
     * Resolve the effective role slugs for a user inside a tenant.
     *
     * @return list<string>
     */
    public static function rolesFor(User $user, string $tenantId): array
    {
        $companyUser = self::membershipFor($user, $tenantId);

        if (! $companyUser) {
            return [];
        }

        return self::effectiveRoles($companyUser);
    }

    /**
     * Resolve the concrete branch ids a user may view data for inside a tenant.
     *
     * A non-HQ membership returns exactly its allowed branches (its own branch
     * plus any explicitly assigned branches). A headquarters membership and a
     * membership without a branch resolve to every active branch.
     *
     * @return list<int>
     */
    public static function accessibleBranchIds(User $user, string $tenantId): array
    {
        $cacheKey = $user->id.':'.$tenantId;

        if (isset(self::$accessibleBranchIds[$cacheKey])) {
            return self::$accessibleBranchIds[$cacheKey];
        }

        $companyUser = self::membershipFor($user, $tenantId);

        if (! $companyUser) {
            return self::$accessibleBranchIds[$cacheKey] = [];
        }

        $allowed = $companyUser->allowedBranchIds();

        if ($allowed !== []) {
            return self::$accessibleBranchIds[$cacheKey] = $allowed;
        }

        $wasInitialized = tenancy()->initialized;

        if (! $wasInitialized) {
            $tenant = Tenant::find($tenantId);

            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }

        try {
            $branchIds = array_values(
                DB::table('branches')
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->pluck('id')
                    ->map(fn ($id): int => (int) $id)
                    ->all(),
            );
        } finally {
            if (! $wasInitialized && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        return self::$accessibleBranchIds[$cacheKey] = $branchIds;
    }

    /**
     * Active branches the user may access, as a public read projection.
     *
     * @return list<object{id: int, name: string, code: string, is_headquarters: bool}>
     */
    public static function accessibleBranches(User $user, string $tenantId): array
    {
        return array_values(
            DB::table('branches')
                ->whereIn('id', self::accessibleBranchIds($user, $tenantId))
                ->orderByDesc('is_headquarters')
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'is_headquarters'])
                ->map(static fn ($branch): object => (object) [
                    'id' => (int) $branch->id,
                    'name' => (string) $branch->name,
                    'code' => (string) $branch->code,
                    'is_headquarters' => (bool) $branch->is_headquarters,
                ])
                ->all(),
        );
    }

    /**
     * Resolve the effective permission slugs for a user inside a tenant.
     *
     * When multiple branch ids are given, the union of permissions granted on
     * those branches (plus company-wide roles) is returned. Passing null
     * resolves only company-wide roles.
     *
     * @param  list<int>|null  $branchIds
     * @return list<string>
     */
    public static function permissionsFor(User $user, string $tenantId, ?array $branchIds): array
    {
        $companyUser = self::membershipFor($user, $tenantId);

        if (! $companyUser) {
            return [];
        }

        if ($companyUser->role === 'owner') {
            return array_values(
                Permission::orderBy('slug')->pluck('slug')
                    ->map(static fn ($slug): string => (string) $slug)
                    ->all(),
            );
        }

        $roleIds = self::effectiveRoleIds($companyUser, $branchIds);

        return array_values(
            Permission::query()
                ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
                ->orderBy('slug')
                ->pluck('slug')
                ->map(static fn ($slug): string => (string) $slug)
                ->all(),
        );
    }

    public static function can(User $user, ?string $tenantId, string $permission): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if (! $tenantId) {
            return false;
        }

        $branchIds = self::contextBranchIds($user, $tenantId);

        return in_array($permission, self::permissionsFor($user, $tenantId, $branchIds), true);
    }

    /**
     * Resolve the branch ids permissions are evaluated against for the current
     * session scope: the single active branch, or every accessible branch.
     *
     * @return list<int>|null
     */
    public static function contextBranchIds(User $user, string $tenantId): ?array
    {
        if (session('branch_scope') === 'branch') {
            $branchId = (int) session('active_branch_id');

            return $branchId ? [$branchId] : [];
        }

        return self::accessibleBranchIds($user, $tenantId);
    }

    private static function membershipFor(User $user, string $tenantId): ?CompanyUser
    {
        return CompanyUser::query()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * @return list<string>
     */
    private static function effectiveRoles(CompanyUser $companyUser): array
    {
        $roles = [$companyUser->role];

        $roles = array_merge(
            $roles,
            $companyUser->companyUserRoles
                ->loadMissing('role')
                ->pluck('role.slug')
                ->all(),
        );

        return array_values(array_unique(array_filter($roles)));
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return Collection<int, int>
     */
    private static function effectiveRoleIds(CompanyUser $companyUser, ?array $branchIds): Collection
    {
        $roleIds = collect();

        $companyRole = Role::where('slug', $companyUser->role)->first();
        if ($companyRole) {
            $roleIds->push($companyRole->id);
        }

        $roleQuery = $companyUser->companyUserRoles()
            ->whereNull('branch_id');

        if ($branchIds !== null && $branchIds !== []) {
            $roleQuery->orWhereIn('branch_id', $branchIds);
        }

        return $roleIds->merge($roleQuery->pluck('role_id'))->unique()->values();
    }
}
