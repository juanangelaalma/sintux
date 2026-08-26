<?php

namespace Modules\Approval\Application;

use Modules\Approval\Models\ApprovalRule;

class GetApprovalRule
{
    public function execute(int $ruleId): ApprovalRule
    {
        return ApprovalRule::with(['transactionType', 'criteria', 'stages.approverAssignments', 'scopedUserAssignments'])
            ->withCount(['mappings as pending_mappings_count' => function ($q) {
                $q->where('overall_status', 'pending');
            }])
            ->findOrFail($ruleId);
    }
}
