<?php

namespace Modules\Company\Application;

use App\Models\CompanyUser;
use App\Models\CompanyUserBranch;
use App\Models\CompanyUserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCompanyUser
{
    /**
     * Create a user in the central schema and link them to a tenant.
     *
     * @param  array{name: string, email: string, password: string, company_role: string, scope: string, branch_id?: int|null, allowed_branch_ids?: array<int, int>, roles?: array<int, int>}  $data
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
            'role' => $data['company_role'] ?? 'member',
            'is_default' => false,
        ]);

        $this->assignBranches($membership, $data['allowed_branch_ids'] ?? []);
        $this->assignRoles($membership, $data['roles'] ?? []);

        return $membership;
    }

    /**
     * @param  array<int, int>  $branchIds
     */
    private function assignBranches(CompanyUser $membership, array $branchIds): void
    {
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
