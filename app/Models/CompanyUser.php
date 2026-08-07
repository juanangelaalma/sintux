<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

class CompanyUser extends Model
{
    use CentralConnection;

    protected $table = 'company_users';

    protected $fillable = [
        'user_id',
        'tenant_id',
        'branch_id',
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

    /**
     * Branches the user is allowed to access when scoped to a branch.
     *
     * @return HasMany<CompanyUserBranch, $this>
     */
    public function allowedBranches(): HasMany
    {
        return $this->hasMany(CompanyUserBranch::class, 'company_user_id');
    }

    /**
     * Resolve the branch ids this membership may access.
     *
     * An empty array means the user has access to every active branch.
     *
     * @return list<int>
     */
    public function allowedBranchIds(): array
    {
        if (! $this->branch_id) {
            return [];
        }

        $wasInitialized = tenancy()->initialized;
        if (! $wasInitialized) {
            $tenant = Tenant::find($this->tenant_id);
            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }

        try {
            $isHq = DB::table('branches')
                ->where('id', $this->branch_id)
                ->value('is_headquarters');
        } finally {
            if (! $wasInitialized && tenancy()->initialized) {
                tenancy()->end();
            }
        }

        if ($isHq) {
            return [];
        }

        return $this->allowedBranches()
            ->pluck('branch_id')
            ->push($this->branch_id)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
