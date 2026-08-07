<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property int $company_user_id
 * @property int $branch_id
 */
class CompanyUserBranch extends Model
{
    use CentralConnection;

    protected $table = 'company_user_branches';

    protected $fillable = [
        'company_user_id',
        'branch_id',
    ];

    /**
     * @return BelongsTo<CompanyUser, $this>
     */
    public function companyUser(): BelongsTo
    {
        return $this->belongsTo(CompanyUser::class, 'company_user_id');
    }
}
