<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\PurchaseOrder;

class ApprovePurchaseOrder
{
    public function execute(int $purchaseOrderId): PurchaseOrder
    {
        $po = PurchaseOrder::with(['items'])->findOrFail($purchaseOrderId);

        if ($po->status !== 'pending') {
            throw ValidationException::withMessages([
                'order' => 'Pesanan pembelian hanya dapat disetujui saat berstatus pending.',
            ]);
        }

        $po->update(['status' => 'approved']);

        return $po->fresh(['items']);
    }
}
