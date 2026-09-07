<?php

namespace Modules\Approval\Application;

use Illuminate\Support\Collection;
use Modules\Approval\Enums\ApprovalStatus;
use Modules\Approval\Models\ApprovalMapping;

class GetPendingApprovals
{
    /**
     * Get all pending approval mappings where the given user is an eligible approver
     * on the current stage and has not yet acted on that stage.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function execute(int $userId): Collection
    {
        $mappings = ApprovalMapping::with(['rule.transactionType', 'rule.stages.approverAssignments', 'actions'])
            ->where('overall_status', ApprovalStatus::Pending)
            ->where('creator_id', '!=', $userId)
            ->get();

        return $mappings
            ->filter(function (ApprovalMapping $mapping) use ($userId) {
                $currentStage = $mapping->rule->stages
                    ->firstWhere('stage_order', $mapping->current_stage_order);

                if (! $currentStage) {
                    return false;
                }

                $approverIds = $currentStage->approverAssignments
                    ->pluck('user_id')
                    ->map(fn ($id) => (int) $id)
                    ->all();

                if (! in_array($userId, $approverIds, true)) {
                    return false;
                }

                $userAlreadyActed = $mapping->actions
                    ->where('approval_stage_id', $currentStage->id)
                    ->where('user_id', $userId)
                    ->isNotEmpty();

                return ! $userAlreadyActed;
            })
            ->map(function (ApprovalMapping $mapping) {
                $currentStage = $mapping->rule->stages
                    ->firstWhere('stage_order', $mapping->current_stage_order);

                return [
                    'id' => $mapping->id,
                    'transaction_type' => $mapping->transaction_type,
                    'transaction_type_label' => $mapping->rule->transactionType->label ?? $mapping->transaction_type,
                    'transaction_id' => $mapping->transaction_id,
                    'document_number' => $mapping->document_number,
                    'creator_id' => $mapping->creator_id,
                    'creator_name' => $mapping->creator_name,
                    'branch_id' => $mapping->branch_id,
                    'total' => (float) $mapping->total,
                    'currency_code' => $mapping->currency_code,
                    'rule_name' => $mapping->rule->name,
                    'current_stage_order' => $mapping->current_stage_order,
                    'approval_type' => $currentStage?->approval_type ?? 'any',
                    'mapped_at' => $mapping->mapped_at?->toIso8601String(),
                ];
            })
            ->values();
    }
}
