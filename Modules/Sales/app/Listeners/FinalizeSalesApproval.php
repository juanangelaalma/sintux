<?php

namespace Modules\Sales\Listeners;

use Modules\Approval\Events\TransactionApprovalFinalized;
use Modules\Sales\Application\SalesInvoice\FinalizeApprovedSalesInvoice;
use Modules\Sales\Enums\SalesInvoiceStatus;
use Modules\Sales\Models\SalesInvoice;
use Modules\Warehouse\Application\StockReservation\ReleaseStock;

class FinalizeSalesApproval
{
    public function handle(TransactionApprovalFinalized $event): void
    {
        if ($event->transactionType !== 'sales_invoice') {
            return;
        }

        $inv = SalesInvoice::with('items')->find($event->transactionId);

        if (! $inv || ! $inv->status->isPending()) {
            return;
        }

        if ($event->status === 'approved') {
            app(FinalizeApprovedSalesInvoice::class)->execute($inv);

            return;
        }

        // Rejected = terminal: lepas reservasi, faktur dibatalkan.
        app(ReleaseStock::class)->execute($inv->id);
        $inv->update(['status' => SalesInvoiceStatus::Cancelled]);
    }
}
