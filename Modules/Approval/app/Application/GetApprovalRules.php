<?php

namespace Modules\Approval\Application;

use Illuminate\Database\Eloquent\Collection;
use Modules\Approval\Enums\ApprovalStatus;
use Modules\Approval\Models\ApprovalRule;

class GetApprovalRules
{
    /**
     * @return Collection<int, ApprovalRule>
     */
    public function execute(?string $transactionTypeKey = null, ?string $search = null): Collection
    {
        $query = ApprovalRule::with(['transactionType', 'criteria', 'stages.approverAssignments', 'scopedUserAssignments'])
            ->withCount(['mappings as pending_mappings_count' => function ($q) {
                $q->where('overall_status', ApprovalStatus::Pending);
            }])
            ->orderBy('id', 'desc');

        if ($transactionTypeKey) {
            $query->whereHas('transactionType', function ($q) use ($transactionTypeKey) {
                $q->where('key', $transactionTypeKey);
            });
        }

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        return $query->get();
    }
}
