<?php

namespace Modules\Company\Application;

use App\Models\CompanyUser;
use App\Models\CompanyUserRole;
use App\Models\User;

class CreateCompanyUser
{
    /**
     * Create a user in the central schema and link them to a tenant.
     *
     * @param  array{name: string, email: string, password: string, company_role: string, scope: string, branch_id?: int|null, roles?: array<int, int>}  $data
     */
    public function execute(string $tenantId, array $data): CompanyUser
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'user',
            'password' => bcrypt($data['password']),
        ]);

        $membership = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenantId,
            'branch_id' => $data['branch_id'] ?? null,
            'scope' => $data['scope'] ?? 'branch',
            'role' => $data['company_role'] ?? 'member',
            'is_default' => false,
        ]);

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
