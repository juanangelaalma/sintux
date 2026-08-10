<?php

namespace Modules\Company\Application;

use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Company\Models\CompanyUserBranch;
use Modules\Company\Models\CompanyUserRole;

class UpdateCompanyUser
{
    /**
     * Update a tenant membership and its role assignments.
     *
     * @param  array{name?: string, password?: string|null, company_role?: string, scope?: string, branch_id?: int|null, allowed_branch_ids?: array<int, int>, roles?: array<int, int>}  $data
     */
    public function execute(int $membershipId, array $data): CompanyUser
    {
        $membership = CompanyUser::findOrFail($membershipId);

        $membership->update([
            'branch_id' => array_key_exists('branch_id', $data) ? $data['branch_id'] : $membership->branch_id,
            'role' => $data['company_role'] ?? $membership->role,
        ]);

        if (array_key_exists('name', $data)) {
            $membership->user?->update(['name' => $data['name']]);
        }

        if (! empty($data['password'])) {
            $membership->user?->update(['password' => bcrypt($data['password'])]);
        }

        $this->assignBranches($membership, $data['allowed_branch_ids'] ?? []);

        $membership->companyUserRoles()->delete();
        $this->assignRoles($membership, $data['roles'] ?? []);

        return $membership;
    }

    /**
     * @param  array<int, int>  $branchIds
     */
    private function assignBranches(CompanyUser $membership, array $branchIds): void
    {
        $membership->allowedBranches()->delete();

        $isHq = DB::table('branches')
            ->where('id', $membership->branch_id)
            ->value('is_headquarters');

        if ($isHq) {
            return;
        }

        foreach ($branchIds as $branchId) {
            CompanyUserBranch::create([
                'company_user_id' => $membership->id,
                'branch_id' => $branchId,
            ]);
        }
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function assignRoles(CompanyUser $membership, array $roleIds): void
    {
        foreach ($roleIds as $roleId) {
            CompanyUserRole::create([
                'company_user_id' => $membership->id,
                'branch_id' => null,
                'role_id' => $roleId,
            ]);
        }
    }
}
