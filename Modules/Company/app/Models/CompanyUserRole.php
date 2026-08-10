<?php

namespace Modules\Company\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property int $company_user_id
 * @property int|null $branch_id
 * @property int $role_id
 */
class CompanyUserRole extends Model
{
    use CentralConnection;

    protected $table = 'company_user_roles';

    protected $fillable = [
        'company_user_id',
        'branch_id',
        'role_id',
    ];

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /**
     * @return BelongsTo<CompanyUser, $this>
     */
    public function companyUser(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'company_user_id');
    }
}
