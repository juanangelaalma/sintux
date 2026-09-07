<?php

namespace Modules\Approval\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approval\Enums\ApprovalStatus;
use Modules\Approval\Events\ApprovalRuleChanged;
use Modules\Approval\Models\ApprovalRule;
use Modules\Approval\Models\ApprovalRuleLog;

class DeleteApprovalRule
{
    public function execute(int $ruleId, int $changedBy): void
    {
        $rule = ApprovalRule::with(['transactionType', 'mappings'])->findOrFail($ruleId);

        $pendingMappings = $rule->mappings()->where('overall_status', ApprovalStatus::Pending)->get();

        if ($pendingMappings->isNotEmpty()) {
            $numbers = $pendingMappings->pluck('document_number')->filter()->implode(', ');
            $msg = 'Aturan tidak dapat dihapus karena masih terhubung dengan transaksi draft yang belum selesai';
            if ($numbers !== '') {
                $msg .= " ({$numbers})";
            }
            $msg .= '.';

            throw ValidationException::withMessages([
                'rule' => $msg,
            ]);
        }

        DB::transaction(function () use ($rule, $changedBy) {
            $beforeState = $rule->load(['transactionType', 'criteria', 'stages.approverAssignments'])->toArray();

            ApprovalRuleLog::create([
                'approval_rule_id' => $rule->id,
                'changed_by' => $changedBy,
                'change_type' => 'deleted',
                'before_value' => $beforeState,
                'after_value' => null,
                'changed_at' => now(),
            ]);

            $typeKey = $rule->transactionType->key;
            $applyToExisting = (bool) $rule->apply_to_existing_draft;

            $rule->delete();

            ApprovalRuleChanged::dispatch($typeKey, $applyToExisting);
        });
    }
}
