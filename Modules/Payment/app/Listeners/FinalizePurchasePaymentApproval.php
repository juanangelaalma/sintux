<?php

namespace Modules\Payment\Listeners;

use Modules\Approval\Events\TransactionApprovalFinalized;
use Modules\Payment\Application\Finalize\FinalizePurchasePayment;
use Modules\Payment\Models\PurchasePayment;

class FinalizePurchasePaymentApproval
{
    public function __construct(
        private readonly FinalizePurchasePayment $finalize,
    ) {}

    public function handle(TransactionApprovalFinalized $event): void
    {
        if ($event->transactionType !== 'purchase_payment') {
            return;
        }

        $payment = PurchasePayment::find($event->transactionId);

        if (! $payment) {
            return;
        }

        if ($event->status === 'approved') {
            $this->finalize->execute((int) $payment->id);

            return;
        }

        $payment->update(['status' => 'rejected']);
    }
}
