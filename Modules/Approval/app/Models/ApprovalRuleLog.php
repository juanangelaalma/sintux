<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRuleLog extends Model
{
    protected $fillable = [
        'approval_rule_id',
        'changed_by',
        'change_type',
        'before_value',
        'after_value',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'before_value' => 'array',
            'after_value' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApprovalRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class, 'approval_rule_id');
    }
}
