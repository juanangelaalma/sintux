<?php

namespace Modules\Approval\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalStage extends Model
{
    protected $fillable = [
        'approval_rule_id',
        'stage_order',
        'approval_type',
    ];

    /**
     * @return BelongsTo<ApprovalRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'approval_rule_id');
    }

    /**
     * @return HasMany<ApprovalStageApprover, $this>
     */
    public function approverAssignments(): HasMany
    {
        return $this->hasMany(ApprovalStageApprover::class, 'approval_stage_id');
    }

    /**
     * Helper relation / accessor for approver users from central connection.
     *
     * @return Collection<int, User>
     */
    public function getApproversAttribute(): Collection
    {
        $userIds = $this->approverAssignments()->pluck('user_id')->all();

        if (empty($userIds)) {
            return new Collection;
        }

        return User::whereIn('id', $userIds)->get();
    }

    /**
     * Sync approver user ids into the tenant pivot table.
     *
     * @param  list<int>  $userIds
     */
    public function syncApprovers(array $userIds): void
    {
        $this->approverAssignments()->delete();

        foreach (array_unique($userIds) as $userId) {
            $this->approverAssignments()->create([
                'user_id' => $userId,
            ]);
        }
    }

    /**
     * @return HasMany<ApprovalAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class, 'approval_stage_id');
    }
}
