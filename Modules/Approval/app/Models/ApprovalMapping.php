<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ApprovalMapping extends Model
{
    protected $fillable = [
        'transaction_type',
        'transaction_id',
        'approval_rule_id',
        'document_number',
        'creator_id',
        'creator_name',
        'branch_id',
        'total',
        'currency_code',
        'current_stage_order',
        'overall_status',
        'mapped_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:4',
            'current_stage_order' => 'integer',
            'mapped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApprovalRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'approval_rule_id')->withTrashed();
    }

    /**
     * @return HasMany<ApprovalAction, $this>
     */
    public function actions(): HasMany
    {
        return $this->hasMany(ApprovalAction::class);
    }
}
