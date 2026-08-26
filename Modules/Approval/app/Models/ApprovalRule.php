<?php

namespace Modules\Approval\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalRule extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'transaction_type_id',
        'name',
        'description',
        'currency_code',
        'scope_all_users',
        'apply_to_existing_draft',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scope_all_users' => 'boolean',
            'apply_to_existing_draft' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<ApprovalTransactionType, $this>
     */
    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(ApprovalTransactionType::class, 'transaction_type_id');
    }

    /**
     * @return HasMany<ApprovalRuleCriteria, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(ApprovalRuleCriteria::class);
    }

    /**
     * @return HasMany<ApprovalStage, $this>
     */
    public function stages(): HasMany
    {
        return $this->hasMany(ApprovalStage::class)->orderBy('stage_order');
    }

    /**
     * @return HasMany<ApprovalRuleScopedUser, $this>
     */
    public function scopedUserAssignments(): HasMany
    {
        return $this->hasMany(ApprovalRuleScopedUser::class, 'approval_rule_id');
    }

    /**
     * @return Collection<int, User>
     */
    public function getScopedUsersAttribute(): Collection
    {
        $userIds = $this->scopedUserAssignments()->pluck('user_id')->all();

        if (empty($userIds)) {
            return new Collection;
        }

        return User::whereIn('id', $userIds)->get();
    }

    public function syncScopedUsers(array $userIds): void
    {
        $this->scopedUserAssignments()->delete();

        foreach (array_unique($userIds) as $userId) {
            $this->scopedUserAssignments()->create([
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * @return HasMany<ApprovalRuleLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ApprovalRuleLog::class)->orderByDesc('changed_at');
    }

    /**
     * @return HasMany<ApprovalMapping, $this>
     */
    public function mappings(): HasMany
    {
        return $this->hasMany(ApprovalMapping::class);
    }
}
