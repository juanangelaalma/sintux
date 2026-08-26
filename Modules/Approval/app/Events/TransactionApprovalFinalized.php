<?php

namespace Modules\Approval\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched when a transaction's approval workflow reaches a final state.
 *
 * The owning module is responsible for updating its own document status.
 */
class TransactionApprovalFinalized
{
    use Dispatchable;

    /**
     * @param  'approved'|'rejected'  $status
     */
    public function __construct(
        public readonly string $transactionType,
        public readonly int $transactionId,
        public readonly string $status,
    ) {}
}
