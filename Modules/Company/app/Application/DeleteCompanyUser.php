<?php

namespace Modules\Company\Application;

use App\Models\CompanyUser;
use RuntimeException;

class DeleteCompanyUser
{
    /**
     * Remove a user's membership. Deletes the central user when they have no
     * other company memberships left.
     */
    public function execute(int $membershipId): void
    {
        $membership = CompanyUser::findOrFail($membershipId);

        if ($membership->role === 'owner') {
            throw new RuntimeException('The company owner cannot be removed.');
        }

        $user = $membership->user;
        $membership->delete();

        if ($user && $user->companyUsers()->count() === 0 && $user->role !== 'superadmin') {
            $user->delete();
        }
    }
}
