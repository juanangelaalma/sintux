<?php

namespace Modules\Approval\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalAction extends Model
{
    protected $fillable = [
        'approval_mapping_id',
        'approval_stage_id',
        'user_id',
        'action',
        'comment',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ApprovalMapping, $this>
     */
    public function mapping(): BelongsTo
    {
        return $this->belongsTo(ApprovalMapping::class, 'approval_mapping_id');
    }

    /**
     * @return BelongsTo<ApprovalStage, $this>
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ApprovalStage::class, 'approval_stage_id');
    }
}
