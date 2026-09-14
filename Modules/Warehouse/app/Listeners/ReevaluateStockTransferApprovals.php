<?php

namespace Modules\Warehouse\Listeners;

use Modules\Approval\Application\ApprovalEngine;
use Modules\Approval\Events\ApprovalRuleChanged;
use Modules\Warehouse\Enums\StockTransferStatus;
use Modules\Warehouse\Models\StockTransfer;

/**
 * Evaluasi ulang transfer pending saat rule approval berubah.
 *
 * - Transfer pending_approval tanpa mapping (fallback HO) bisa
 *   mendapat mapping baru jika rule dibuat/diaktifkan.
 * - Transfer pending_approval dengan mapping bisa lepas mapping
 *   (qty di bawah threshold / rule nonaktif) -> jadi draft
 *   bila masih ada rule aktif, atau tetap fallback bila
 *   tidak ada rule aktif sama sekali.
 */
class ReevaluateStockTransferApprovals
{
    public function __construct(
        private readonly ApprovalEngine $engine,
    ) {}

    public function handle(ApprovalRuleChanged $event): void
    {
        if ($event->transactionType !== 'stock_transfer') {
            return;
        }

        if (! $event->applyToExistingDraft) {
            return;
        }

        $transfers = StockTransfer::with(['items', 'fromWarehouse'])
            ->where('status', StockTransferStatus::PendingApproval->value)
            ->get();

        foreach ($transfers as $transfer) {
            $totalQty = (float) $transfer->items->sum(fn ($item) => (float) $item->qty);

            $mapping = $this->engine->evaluateAndMap([
                'transaction_type' => 'stock_transfer',
                'transaction_id' => $transfer->id,
                'document_number' => 'ST-'.$transfer->id,
                'created_by' => (int) ($transfer->created_by ?? 0),
                'total' => $totalQty,
                'currency_code' => 'QTY',
                'branch_id' => (int) $transfer->fromWarehouse?->branch_id,
            ]);

            if ($mapping) {
                continue;
            }

            // Tidak ada mapping: di bawah threshold sedangkan
            // rule masih ada -> tidak butuh approval lagi.
            if ($this->engine->hasActiveRules('stock_transfer')) {
                $transfer->update(['status' => StockTransferStatus::Draft->value]);
            }
        }
    }
}
