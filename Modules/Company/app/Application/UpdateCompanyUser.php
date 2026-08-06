<?php

namespace Modules\Company\Application;

use App\Models\CompanyUser;
use App\Models\CompanyUserRole;

class UpdateCompanyUser
{
    /**
     * Update a tenant membership and its role assignments.
     *
     * @param  array{name?: string, password?: string|null, company_role?: string, scope?: string, branch_id?: int|null, roles?: array<int, int>}  $data
     */
    public function execute(int $membershipId, array $data): CompanyUser
    {
        $membership = CompanyUser::findOrFail($membershipId);

        $membership->update([
            'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $membership->branch_id,
            'scope' => $data['scope'] ?? $membership->scope,
            'role' => $data['company_role'] ?? $membership->role,
        ]);

        if (array_key_exists('name', $data)) {
            $membership->user?->update(['name' => $data['name']]);
        }

        if (! empty($data['password'])) {
            $membership->user?->update(['password' => bcrypt($data['password'])]);
        }

        $membership->companyUserRoles()->delete();
        $this->assignRoles($membership, $data['roles'] ?? []);

        return $membership;
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function assignRoles(CompanyUser $membership, array $roleIds): void
    {
        $assignmentBranchId = $membership->isBranchScoped() ? $membership->branch_id : null;

        foreach ($roleIds as $roleId) {
            CompanyUserRole::create([
                'company_user_id' => $membership->id,
                'branch_id' => $assignmentBranchId,
                'role_id' => $roleId,
            ]);
        }
    }
}
