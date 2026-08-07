<?php

namespace Modules\Company\Application;

use App\Models\CompanyUser;

class GetCompanyUsers
{
    /**
     * List all members of a tenant with their user, branch and roles.
     *
     * @return list<array<string, mixed>>
     */
    public function execute(string $tenantId): array
    {
        return CompanyUser::with(['user', 'companyUserRoles.role', 'allowedBranches'])
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (CompanyUser $membership) => [
                'id' => $membership->id,
                'user_id' => $membership->user_id,
                'name' => $membership->user?->name,
                'email' => $membership->user?->email,
                'company_role' => $membership->role,
                'branch_id' => $membership->branch_id,
                'allowed_branch_ids' => $membership->allowedBranches
                    ->pluck('branch_id')
                    ->all(),
                'is_default' => $membership->is_default,
                'roles' => $membership->companyUserRoles
                    ->pluck('role.slug')
                    ->all(),
            ])
            ->all();
    }
}
