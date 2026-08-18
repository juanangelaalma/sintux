<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\PurchaseOrder;

class CancelPurchaseOrder
{
    public function execute(int $purchaseOrderId): PurchaseOrder
    {
        $po = PurchaseOrder::with(['items'])->findOrFail($purchaseOrderId);

        if (! in_array($po->status, ['draft', 'pending', 'approved', 'sent'], true)) {
            throw ValidationException::withMessages([
                'order' => 'Pesanan pembelian yang sudah diterima atau diproses tidak dapat dibatalkan.',
            ]);
        }

        $po->update(['status' => 'cancelled']);

        return $po->fresh(['items']);
    }
}
