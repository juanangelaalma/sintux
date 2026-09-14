<?php

namespace Modules\Warehouse\Listeners;

use Modules\Approval\Events\TransactionApprovalFinalized;
use Modules\Warehouse\Enums\StockTransferStatus;
use Modules\Warehouse\Models\StockTransfer;

/**
 * Finalisasi status transfer stok saat workflow approval selesai.
 *
 * - approved -> draft (siap ship, stok final dicek saat ship via FIFO).
 * - rejected -> rejected.
 *
 * Hanya transisi dari pending_approval agar tidak menimpa
 * transfer yang sudah ship/receive.
 */
class FinalizeStockTransferApproval
{
    public function handle(TransactionApprovalFinalized $event): void
    {
        if ($event->transactionType !== 'stock_transfer') {
            return;
        }

        $transfer = StockTransfer::find($event->transactionId);

        if (! $transfer) {
            return;
        }

        if ($transfer->status !== StockTransferStatus::PendingApproval->value) {
            return;
        }

        if ($event->status === 'approved') {
            $transfer->update([
                'status' => StockTransferStatus::Draft->value,
                'approved_at' => now(),
            ]);

            return;
        }

        if ($event->status === 'rejected') {
            $transfer->update([
                'status' => StockTransferStatus::Rejected->value,
                'approved_at' => now(),
            ]);
        }
    }
}
