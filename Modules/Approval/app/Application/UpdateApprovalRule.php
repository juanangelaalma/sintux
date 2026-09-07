<?php

namespace Modules\Approval\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approval\Enums\ApprovalStatus;
use Modules\Approval\Events\ApprovalRuleChanged;
use Modules\Approval\Models\ApprovalRule;
use Modules\Approval\Models\ApprovalRuleLog;

class UpdateApprovalRule
{
    /**
     * @param  array{
     *     name?: string,
     *     description?: string|null,
     *     currency_code?: string,
     *     scope_all_users?: bool,
     *     scoped_user_ids?: list<int>,
     *     apply_to_existing_draft?: bool,
     *     is_active?: bool,
     *     min_amount?: float|int,
     *     stages?: list<array{approval_type?: 'any'|'all', approver_ids: list<int>}>
     * }  $data
     */
    public function execute(int $ruleId, array $data, int $changedBy): ApprovalRule
    {
        $rule = ApprovalRule::with(['transactionType', 'criteria', 'stages.approverAssignments', 'scopedUserAssignments', 'mappings'])
            ->findOrFail($ruleId);

        $beforeState = $rule->toArray();
        $hasPendingDrafts = $rule->mappings()->where('overall_status', ApprovalStatus::Pending)->exists();

        if ($hasPendingDrafts) {
            // Field-lock: if pending drafts exist, only approver lists can be changed
            if (isset($data['name']) && $data['name'] !== $rule->name) {
                throw ValidationException::withMessages([
                    'rule' => 'Aturan memiliki transaksi draft yang belum selesai; hanya daftar approver yang dapat diubah.',
                ]);
            }

            if (isset($data['min_amount'])) {
                $currentMin = (float) ($rule->criteria->firstWhere('criteria_type', 'amount')?->min_amount ?? 0);
                if ((float) $data['min_amount'] !== $currentMin) {
                    throw ValidationException::withMessages([
                        'rule' => 'Aturan memiliki transaksi draft yang belum selesai; kriteria nominal tidak dapat diubah.',
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($rule, $data, $changedBy, $beforeState, $hasPendingDrafts) {
            if (! $hasPendingDrafts) {
                $rule->update(array_filter([
                    'name' => $data['name'] ?? $rule->name,
                    'description' => array_key_exists('description', $data) ? $data['description'] : $rule->description,
                    'currency_code' => $data['currency_code'] ?? $rule->currency_code,
                    'scope_all_users' => $data['scope_all_users'] ?? $rule->scope_all_users,
                    'apply_to_existing_draft' => $data['apply_to_existing_draft'] ?? $rule->apply_to_existing_draft,
                    'is_active' => $data['is_active'] ?? $rule->is_active,
                ], fn ($v) => $v !== null));

                if (isset($data['scope_all_users']) && ! $data['scope_all_users'] && isset($data['scoped_user_ids'])) {
                    $rule->syncScopedUsers($data['scoped_user_ids']);
                } elseif (isset($data['scope_all_users']) && $data['scope_all_users']) {
                    $rule->scopedUserAssignments()->delete();
                }

                if (isset($data['min_amount'])) {
                    $amountCriteria = $rule->criteria->firstWhere('criteria_type', 'amount');
                    if ($amountCriteria) {
                        $amountCriteria->update(['min_amount' => $data['min_amount']]);
                    }
                }
            }

            if (isset($data['stages'])) {
                foreach ($data['stages'] as $index => $stageData) {
                    $stageOrder = $index + 1;
                    $stage = $rule->stages->firstWhere('stage_order', $stageOrder);

                    if ($stage) {
                        if (! $hasPendingDrafts && isset($stageData['approval_type'])) {
                            $stage->update(['approval_type' => $stageData['approval_type']]);
                        }

                        if (isset($stageData['approver_ids'])) {
                            $stage->syncApprovers($stageData['approver_ids']);
                        }
                    }
                }
            }

            $afterState = $rule->fresh(['transactionType', 'criteria', 'stages.approverAssignments', 'scopedUserAssignments'])->toArray();

            ApprovalRuleLog::create([
                'approval_rule_id' => $rule->id,
                'changed_by' => $changedBy,
                'change_type' => 'updated',
                'before_value' => $beforeState,
                'after_value' => $afterState,
                'changed_at' => now(),
            ]);

            ApprovalRuleChanged::dispatch(
                $rule->transactionType->key,
                (bool) $rule->apply_to_existing_draft,
            );

            return $rule->fresh(['transactionType', 'criteria', 'stages.approverAssignments', 'scopedUserAssignments']);
        });
    }
}
