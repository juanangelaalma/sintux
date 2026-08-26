<?php

namespace Modules\Approval\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after an approval rule is created, updated, or deleted so that
 * owning modules can re-evaluate their pending drafts when the rule's
 * "apply to existing draft" toggle is enabled.
 */
class ApprovalRuleChanged
{
    use Dispatchable;

    public function __construct(
        public readonly string $transactionType,
        public readonly bool $applyToExistingDraft,
    ) {}
}
