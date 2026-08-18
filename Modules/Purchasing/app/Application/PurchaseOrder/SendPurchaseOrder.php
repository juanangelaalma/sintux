<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\PurchaseOrder;

class SendPurchaseOrder
{
    public function execute(int $purchaseOrderId): PurchaseOrder
    {
        $po = PurchaseOrder::with(['items'])->findOrFail($purchaseOrderId);

        if ($po->status !== 'approved') {
            throw ValidationException::withMessages([
                'order' => 'Pesanan pembelian hanya dapat dikirim saat berstatus disetujui (approved).',
            ]);
        }

        $po->update(['status' => 'sent']);

        return $po->fresh(['items']);
    }
}
