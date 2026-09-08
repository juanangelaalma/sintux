<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Models\PurchaseOrder;

class CancelPurchaseOrder
{
    public function execute(int $purchaseOrderId): PurchaseOrder
    {
        $po = PurchaseOrder::with(['items'])->findOrFail($purchaseOrderId);

        if (! $po->status->canCancel()) {
            throw ValidationException::withMessages([
                'order' => 'Pesanan pembelian yang sudah diterima atau diproses tidak dapat dibatalkan.',
            ]);
        }

        $po->update(['status' => PurchaseOrderStatus::Cancelled]);

        return $po->fresh(['items']);
    }
}
