<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CompanyUser extends Model
{
    use CentralConnection;

    protected $table = 'company_users';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'branch_id',
        'scope',
        'role',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'branch_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Role assignments scoped per branch.
     *
     * @return HasMany<CompanyUserRole, $this>
     */
    public function companyUserRoles(): HasMany
    {
        return $this->hasMany(CompanyUserRole::class, 'company_user_id');
    }

    public function isBranchScoped(): bool
    {
        return $this->scope === 'branch';
    }
}
