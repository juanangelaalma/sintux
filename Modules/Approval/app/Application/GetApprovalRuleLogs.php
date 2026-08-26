<?php

namespace Modules\Approval\Application;

use Illuminate\Database\Eloquent\Collection;
use Modules\Approval\Models\ApprovalRuleLog;

class GetApprovalRuleLogs
{
    /**
     * @return Collection<int, ApprovalRuleLog>
     */
    public function execute(int $ruleId): Collection
    {
        return ApprovalRuleLog::where('approval_rule_id', $ruleId)
            ->orderByDesc('changed_at')
            ->get();
    }
}
