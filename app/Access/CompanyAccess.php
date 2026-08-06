<?php

namespace App\Access;

use App\Models\CompanyUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

class CompanyAccess
{
    /**
     * Resolve the effective role slugs for a user inside a tenant.
     *
     * @return list<string>
     */
    public static function rolesFor(User $user, string $tenantId): array
    {
        $companyUser = $user->companyUserFor($tenantId);

        if (! $companyUser) {
            return [];
        }

        return self::effectiveRoles($companyUser);
    }

    /**
     * Resolve the effective permission slugs for a user inside a tenant + branch.
     *
     * @return list<string>
     */
    public static function permissionsFor(User $user, string $tenantId, ?int $branchId): array
    {
        $companyUser = $user->companyUserFor($tenantId);

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

        $roleIds = self::effectiveRoleIds($companyUser, $branchId);

        return array_values(
            Permission::query()
                ->whereHas('roles', fn ($query) => $query->whereIn('roles.id', $roleIds))
                ->orderBy('slug')
                ->pluck('slug')
                ->map(static fn ($slug): string => (string) $slug)
                ->all(),
        );
    }

    public static function can(User $user, ?string $tenantId, ?int $branchId, string $permission): bool
    {
        if ($user->role === 'superadmin') {
            return true;
        }

        if (! $tenantId) {
            return false;
        }

        return in_array($permission, self::permissionsFor($user, $tenantId, $branchId), true);
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
     * @return Collection<int, int>
     */
    private static function effectiveRoleIds(CompanyUser $companyUser, ?int $branchId): Collection
    {
        $roleIds = collect();

        $companyRole = Role::where('slug', $companyUser->role)->first();
        if ($companyRole) {
            $roleIds->push($companyRole->id);
        }

        $branchRoleIds = $companyUser->companyUserRoles()
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $branchId))
            ->pluck('role_id');

        return $roleIds->merge($branchRoleIds)->unique()->values();
    }
}
