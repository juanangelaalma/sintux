<?php

namespace Modules\Approval\Application;

use Modules\Approval\Enums\ApprovalStatus;
use Modules\Approval\Models\ApprovalComment;
use Modules\Approval\Models\ApprovalMapping;

class GetTransactionApprovalStatus
{
    /**
     * @return array<string, mixed>|null
     */
    public function execute(string $transactionType, int $transactionId, ?int $currentUserId = null): ?array
    {
        $mapping = ApprovalMapping::with([
            'rule.transactionType',
            'rule.stages.approverAssignments',
            'actions',
        ])
            ->where('transaction_type', $transactionType)
            ->where('transaction_id', $transactionId)
            ->first();

        if (! $mapping) {
            return null;
        }

        $currentStage = $mapping->rule->stages
            ->firstWhere('stage_order', $mapping->current_stage_order);

        $canUserApprove = false;
        if ($currentUserId && $mapping->overall_status === ApprovalStatus::Pending && (int) $mapping->creator_id !== $currentUserId && $currentStage) {
            $approverIds = $currentStage->approverAssignments->pluck('user_id')->map(fn ($id) => (int) $id)->all();
            $alreadyActed = $mapping->actions
                ->where('approval_stage_id', $currentStage->id)
                ->where('user_id', $currentUserId)
                ->isNotEmpty();

            $canUserApprove = in_array($currentUserId, $approverIds, true) && ! $alreadyActed;
        }

        $stagesData = $mapping->rule->stages->map(function ($stage) use ($mapping) {
            $actions = $mapping->actions
                ->where('approval_stage_id', $stage->id)
                ->map(fn ($action) => [
                    'id' => $action->id,
                    'user_id' => $action->user_id,
                    'action' => $action->action,
                    'comment' => $action->comment,
                    'acted_at' => $action->acted_at?->toIso8601String(),
                ])
                ->values()
                ->all();

            return [
                'id' => $stage->id,
                'stage_order' => $stage->stage_order,
                'approval_type' => $stage->approval_type,
                'approvers' => $stage->approvers->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                ])->values()->all(),
                'actions' => $actions,
            ];
        })->values()->all();

        $comments = ApprovalComment::where('transaction_type', $transactionType)
            ->where('transaction_id', $transactionId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'user_id' => $c->user_id,
                'user_name' => $c->user_name,
                'content' => $c->content,
                'created_at' => $c->created_at?->toIso8601String(),
                'date_label' => $c->created_at?->format('d M Y'),
                'time_label' => $c->created_at?->format('H:i'),
            ])
            ->values()
            ->all();

        return [
            'mapping_id' => $mapping->id,
            'rule_id' => $mapping->rule_id,
            'rule_name' => $mapping->rule->name,
            'transaction_type' => $mapping->transaction_type,
            'transaction_type_label' => $mapping->rule->transactionType->label ?? $mapping->transaction_type,
            'transaction_id' => $mapping->transaction_id,
            'document_number' => $mapping->document_number,
            'creator_id' => $mapping->creator_id,
            'creator_name' => $mapping->creator_name,
            'current_stage_order' => $mapping->current_stage_order,
            'overall_status' => $mapping->overall_status instanceof ApprovalStatus ? $mapping->overall_status->value : $mapping->overall_status,
            'can_user_approve' => $canUserApprove,
            'stages' => $stagesData,
            'comments' => $comments,
            'comments_count' => count($comments),
            'mapped_at' => $mapping->mapped_at?->toIso8601String(),
        ];
    }
}
