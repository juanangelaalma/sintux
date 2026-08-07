<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Access\CompanyAccess;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'role', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use CentralConnection, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get user companies memberships.
     *
     * @return HasMany<CompanyUser, $this>
     */
    public function companyUsers(): HasMany
    {
        return $this->hasMany(CompanyUser::class, 'user_id');
    }

    /**
     * Resolve the default company membership for this user.
     */
    public function defaultCompany(): ?CompanyUser
    {
        return $this->companyUsers()
            ->where('is_default', true)
            ->first()
            ?? $this->companyUsers()
                ->first();
    }

    /**
     * Resolve the user's membership for a given tenant.
     */
    public function companyUserFor(string $tenantId): ?CompanyUser
    {
        return $this->companyUsers()
            ->where('tenant_id', $tenantId)
            ->first();
    }

    /**
     * Check a permission against the active tenant + branch context.
     */
    public function hasPermissionTo(string $permission, ?string $tenantId = null, ?int $branchId = null): bool
    {
        $tenantId ??= session('active_tenant_id');
        $branchId ??= session('active_branch_id');

        return CompanyAccess::can(
            $this,
            $tenantId ? (string) $tenantId : null,
            $branchId ? (int) $branchId : null,
            $permission,
        );
    }
}
