<?php

namespace Modules\Approval\Application;

use Illuminate\Support\Facades\DB;
use Modules\Approval\Models\ApprovalMapping;
use Modules\Approval\Models\ApprovalRule;
use Modules\Approval\Models\ApprovalTransactionType;

class ApprovalEngine
{
    /**
     * Evaluate a transaction's facts against active approval rules and create/update/delete mapping.
     *
     * @param  array{
     *     transaction_type: string,
     *     transaction_id: int,
     *     document_number?: string|null,
     *     created_by: int,
     *     created_by_name?: string|null,
     *     branch_id?: int|null,
     *     total: float|int,
     *     currency_code?: string
     * }  $facts
     */
    public function evaluateAndMap(array $facts): ?ApprovalMapping
    {
        $typeKey = (string) $facts['transaction_type'];
        $transactionId = (int) $facts['transaction_id'];
        $total = (float) ($facts['total'] ?? 0);
        $currencyCode = (string) ($facts['currency_code'] ?? 'IDR');
        $creatorId = (int) $facts['created_by'];

        // When re-evaluating an existing transaction (e.g. triggered by a rule change),
        // the caller may not know the original creator. Resolve it from the stored mapping
        // so scoped-user matching and the self-approval guard stay correct.
        if ($creatorId <= 0) {
            $existing = ApprovalMapping::where('transaction_type', $typeKey)
                ->where('transaction_id', $transactionId)
                ->first();

            if ($existing) {
                $creatorId = (int) $existing->creator_id;
                $facts['created_by_name'] ??= $existing->creator_name;
            }
        }

        $type = ApprovalTransactionType::where('key', $typeKey)->first();

        if (! $type) {
            return $this->clearPendingMappingIfExists($typeKey, $transactionId);
        }

        $rules = ApprovalRule::query()
            ->where('transaction_type_id', $type->id)
            ->where('is_active', true)
            ->with(['criteria', 'stages.approverAssignments', 'scopedUserAssignments'])
            ->get();

        $matchingRule = $rules
            ->filter(function (ApprovalRule $rule) use ($currencyCode, $creatorId, $total) {
                if (strtoupper($rule->currency_code) !== strtoupper($currencyCode)) {
                    return false;
                }

                if (! $rule->scope_all_users) {
                    $scopedIds = $rule->scopedUserAssignments->pluck('user_id')->map(fn ($id) => (int) $id)->all();
                    if (! in_array($creatorId, $scopedIds, true)) {
                        return false;
                    }
                }

                $amountCriteria = $rule->criteria->firstWhere('criteria_type', 'amount');
                if (! $amountCriteria) {
                    return false;
                }

                $minAmount = (float) $amountCriteria->min_amount;

                return $total > $minAmount;
            })
            ->sortByDesc(function (ApprovalRule $rule) {
                $amountCriteria = $rule->criteria->firstWhere('criteria_type', 'amount');

                return (float) ($amountCriteria?->min_amount ?? 0);
            })
            ->first();

        if (! $matchingRule) {
            return $this->clearPendingMappingIfExists($typeKey, $transactionId);
        }

        // Determine effective stages by excluding creator from approvers
        $effectiveStages = $matchingRule->stages->map(function ($stage) use ($creatorId) {
            $effectiveApproverIds = $stage->approverAssignments
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn (int $id) => $id === $creatorId)
                ->values()
                ->all();

            return [
                'stage' => $stage,
                'effective_approvers' => $effectiveApproverIds,
            ];
        })->filter(fn ($item) => count($item['effective_approvers']) > 0)->values();

        if ($effectiveStages->isEmpty()) {
            // Self-approval guard: creator was sole approver for all stages → auto-satisfied
            return $this->clearPendingMappingIfExists($typeKey, $transactionId);
        }

        $firstEffectiveStageOrder = $effectiveStages->first()['stage']->stage_order;

        return DB::transaction(function () use ($typeKey, $transactionId, $matchingRule, $facts, $firstEffectiveStageOrder, $creatorId) {
            $existingMapping = ApprovalMapping::where('transaction_type', $typeKey)
                ->where('transaction_id', $transactionId)
                ->first();

            if ($existingMapping) {
                if ($existingMapping->overall_status !== 'pending') {
                    return $existingMapping;
                }

                if ((int) $existingMapping->approval_rule_id === (int) $matchingRule->id) {
                    // Update snapshot fields if changed
                    $existingMapping->update([
                        'document_number' => $facts['document_number'] ?? $existingMapping->document_number,
                        'total' => $facts['total'] ?? $existingMapping->total,
                        'currency_code' => $facts['currency_code'] ?? $existingMapping->currency_code,
                        'creator_name' => $facts['created_by_name'] ?? $existingMapping->creator_name,
                        'branch_id' => $facts['branch_id'] ?? $existingMapping->branch_id,
                    ]);

                    return $existingMapping;
                }

                // Remap to new matching rule
                $existingMapping->actions()->delete();
                $existingMapping->update([
                    'approval_rule_id' => $matchingRule->id,
                    'current_stage_order' => $firstEffectiveStageOrder,
                    'document_number' => $facts['document_number'] ?? $existingMapping->document_number,
                    'total' => $facts['total'] ?? $existingMapping->total,
                    'currency_code' => $facts['currency_code'] ?? $existingMapping->currency_code,
                    'creator_name' => $facts['created_by_name'] ?? $existingMapping->creator_name,
                    'branch_id' => $facts['branch_id'] ?? $existingMapping->branch_id,
                    'mapped_at' => now(),
                ]);

                return $existingMapping->fresh(['rule', 'actions']);
            }

            return ApprovalMapping::create([
                'transaction_type' => $typeKey,
                'transaction_id' => $transactionId,
                'approval_rule_id' => $matchingRule->id,
                'document_number' => $facts['document_number'] ?? null,
                'creator_id' => $creatorId,
                'creator_name' => $facts['created_by_name'] ?? null,
                'branch_id' => $facts['branch_id'] ?? null,
                'total' => $facts['total'] ?? null,
                'currency_code' => $facts['currency_code'] ?? 'IDR',
                'current_stage_order' => $firstEffectiveStageOrder,
                'overall_status' => 'pending',
                'mapped_at' => now(),
            ]);
        });
    }

    private function clearPendingMappingIfExists(string $typeKey, int $transactionId): ?ApprovalMapping
    {
        $existing = ApprovalMapping::where('transaction_type', $typeKey)
            ->where('transaction_id', $transactionId)
            ->first();

        if ($existing && $existing->overall_status === 'pending') {
            $existing->actions()->delete();
            $existing->delete();
        }

        return null;
    }
}
