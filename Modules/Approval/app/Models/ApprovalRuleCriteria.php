<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRuleCriteria extends Model
{
    protected $table = 'approval_rule_criteria';

    protected $fillable = [
        'approval_rule_id',
        'criteria_type',
        'min_amount',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:4',
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
