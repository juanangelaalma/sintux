<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalStageApprover extends Model
{
    protected $fillable = [
        'approval_stage_id',
        'user_id',
    ];

    /**
     * @return BelongsTo<ApprovalStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ApprovalStage::class, 'approval_stage_id');
    }
}
