<?php

namespace Modules\Approval\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approval\Events\ApprovalRuleChanged;
use Modules\Approval\Models\ApprovalRule;
use Modules\Approval\Models\ApprovalRuleCriteria;
use Modules\Approval\Models\ApprovalRuleLog;
use Modules\Approval\Models\ApprovalStage;

class CreateApprovalRule
{
    /**
     * @param  array{
     *     transaction_type_id: int,
     *     name: string,
     *     description?: string|null,
     *     currency_code?: string,
     *     scope_all_users?: bool,
     *     scoped_user_ids?: list<int>,
     *     apply_to_existing_draft?: bool,
     *     min_amount: float|int,
     *     stages: list<array{approval_type: 'any'|'all', approver_ids: list<int>}>
     * }  $data
     */
    public function execute(array $data, int $createdBy): ApprovalRule
    {
        $maxRules = (int) config('approval.max_rules_per_company', 25);
        $currentRulesCount = ApprovalRule::whereNull('deleted_at')->count();

        if ($currentRulesCount >= $maxRules) {
            throw ValidationException::withMessages([
                'rule' => "Batas maksimal {$maxRules} aturan approval per perusahaan telah tercapai.",
            ]);
        }

        $stagesCount = count($data['stages'] ?? []);
        $maxStages = (int) config('approval.max_stages_per_rule', 2);

        if ($stagesCount < 1 || $stagesCount > $maxStages) {
            throw ValidationException::withMessages([
                'stages' => "Jumlah tahap persetujuan harus antara 1 dan {$maxStages}.",
            ]);
        }

        $transactionTypeId = (int) $data['transaction_type_id'];

        $transactionTypeKey = DB::table('approval_transaction_types')
            ->where('id', $transactionTypeId)
            ->value('key');

        if (! $transactionTypeKey) {
            throw ValidationException::withMessages([
                'transaction_type_id' => 'Tipe transaksi tidak valid.',
            ]);
        }

        if ($transactionTypeKey === 'purchase_request') {
            $existingPRRule = ApprovalRule::where('transaction_type_id', $transactionTypeId)
                ->where('is_active', true)
                ->whereNull('deleted_at')
                ->exists();

            if ($existingPRRule) {
                throw ValidationException::withMessages([
                    'transaction_type_id' => 'Tipe transaksi Permintaan Pembelian hanya boleh memiliki 1 aturan aktif.',
                ]);
            }
        }

        foreach ($data['stages'] as $index => $stageData) {
            $approverIds = $stageData['approver_ids'] ?? [];
            if (count($approverIds) < 1) {
                $stageNum = $index + 1;
                throw ValidationException::withMessages([
                    "stages.{$index}.approver_ids" => "Tahap {$stageNum} harus memiliki minimal 1 approver.",
                ]);
            }
        }

        return DB::transaction(function () use ($data, $createdBy, $transactionTypeKey) {
            $rule = ApprovalRule::create([
                'transaction_type_id' => $data['transaction_type_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'currency_code' => $data['currency_code'] ?? 'IDR',
                'scope_all_users' => $data['scope_all_users'] ?? true,
                'apply_to_existing_draft' => $data['apply_to_existing_draft'] ?? true,
                'is_active' => true,
                'created_by' => $createdBy,
            ]);

            if (! ($data['scope_all_users'] ?? true) && ! empty($data['scoped_user_ids'])) {
                $rule->syncScopedUsers($data['scoped_user_ids']);
            }

            ApprovalRuleCriteria::create([
                'approval_rule_id' => $rule->id,
                'criteria_type' => 'amount',
                'min_amount' => $data['min_amount'],
                'sequence' => 1,
            ]);

            foreach ($data['stages'] as $index => $stageData) {
                $stage = ApprovalStage::create([
                    'approval_rule_id' => $rule->id,
                    'stage_order' => $index + 1,
                    'approval_type' => $stageData['approval_type'] ?? 'any',
                ]);

                $stage->syncApprovers($stageData['approver_ids']);
            }

            $loadedRule = $rule->load(['transactionType', 'criteria', 'stages.approverAssignments', 'scopedUserAssignments']);

            ApprovalRuleLog::create([
                'approval_rule_id' => $rule->id,
                'changed_by' => $createdBy,
                'change_type' => 'created',
                'before_value' => null,
                'after_value' => $loadedRule->toArray(),
                'changed_at' => now(),
            ]);

            ApprovalRuleChanged::dispatch(
                $transactionTypeKey,
                (bool) $rule->apply_to_existing_draft,
            );

            return $loadedRule;
        });
    }
}
