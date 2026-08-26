<?php

namespace Modules\Approval\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approval\Events\TransactionApprovalFinalized;
use Modules\Approval\Models\ApprovalAction;
use Modules\Approval\Models\ApprovalMapping;

class PerformApprovalAction
{
    /**
     * Perform an approve or reject action on a pending approval mapping.
     *
     * @param  'approve'|'reject'  $action
     */
    public function execute(int $mappingId, int $userId, string $action, ?string $comment = null): ApprovalMapping
    {
        if (! in_array($action, ['approve', 'reject'], true)) {
            throw ValidationException::withMessages([
                'action' => 'Tindakan approval tidak valid.',
            ]);
        }

        return DB::transaction(function () use ($mappingId, $userId, $action, $comment) {
            $mapping = ApprovalMapping::with(['rule.stages.approverAssignments', 'actions'])
                ->where('id', $mappingId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($mapping->overall_status !== 'pending') {
                throw ValidationException::withMessages([
                    'approval' => 'Transaksi ini sudah selesai diproses dan tidak membutuhkan persetujuan lagi.',
                ]);
            }

            if ((int) $mapping->creator_id === $userId) {
                throw ValidationException::withMessages([
                    'approval' => 'Pembuat transaksi tidak boleh menyetujui transaksinya sendiri.',
                ]);
            }

            $currentStage = $mapping->rule->stages
                ->firstWhere('stage_order', $mapping->current_stage_order);

            if (! $currentStage) {
                throw ValidationException::withMessages([
                    'approval' => 'Tahap approval tidak ditemukan.',
                ]);
            }

            $approverIds = $currentStage->approverAssignments
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (! in_array($userId, $approverIds, true)) {
                throw ValidationException::withMessages([
                    'approval' => 'Anda bukan approver pada tahap persetujuan ini.',
                ]);
            }

            $existingUserAction = $mapping->actions
                ->where('approval_stage_id', $currentStage->id)
                ->where('user_id', $userId)
                ->first();

            if ($existingUserAction) {
                throw ValidationException::withMessages([
                    'approval' => 'Anda sudah memberikan keputusan pada tahap persetujuan ini.',
                ]);
            }

            // Create action record
            ApprovalAction::create([
                'approval_mapping_id' => $mapping->id,
                'approval_stage_id' => $currentStage->id,
                'user_id' => $userId,
                'action' => $action,
                'comment' => $comment,
                'acted_at' => now(),
            ]);

            if ($action === 'reject') {
                $mapping->update([
                    'overall_status' => 'rejected',
                ]);

                TransactionApprovalFinalized::dispatch(
                    $mapping->transaction_type,
                    (int) $mapping->transaction_id,
                    'rejected',
                );

                return $mapping->fresh(['rule', 'actions']);
            }

            // Check if current stage is complete
            $effectiveApprovers = array_values(array_diff($approverIds, [$mapping->creator_id]));
            $stageActions = ApprovalAction::where('approval_mapping_id', $mapping->id)
                ->where('approval_stage_id', $currentStage->id)
                ->where('action', 'approve')
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->all();

            $stageCompleted = false;
            if ($currentStage->approval_type === 'any') {
                $stageCompleted = count($stageActions) >= 1;
            } else { // 'all'
                $stageCompleted = count(array_intersect($effectiveApprovers, $stageActions)) >= count($effectiveApprovers);
            }

            if ($stageCompleted) {
                // Find next stage that has effective approvers
                $creatorId = (int) $mapping->creator_id;
                $nextStage = $mapping->rule->stages
                    ->where('stage_order', '>', $mapping->current_stage_order)
                    ->first(function ($stage) use ($creatorId) {
                        $eff = $stage->approverAssignments->pluck('user_id')->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === $creatorId);

                        return $eff->isNotEmpty();
                    });

                if ($nextStage) {
                    $mapping->update([
                        'current_stage_order' => $nextStage->stage_order,
                    ]);
                } else {
                    $mapping->update([
                        'overall_status' => 'approved',
                    ]);

                    TransactionApprovalFinalized::dispatch(
                        $mapping->transaction_type,
                        (int) $mapping->transaction_id,
                        'approved',
                    );
                }
            }

            return $mapping->fresh(['rule', 'actions']);
        });
    }
}
