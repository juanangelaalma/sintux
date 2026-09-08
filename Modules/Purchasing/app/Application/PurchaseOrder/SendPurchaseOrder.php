<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Models\PurchaseOrder;

class SendPurchaseOrder
{
    public function execute(int $purchaseOrderId): PurchaseOrder
    {
        $po = PurchaseOrder::with(['items'])->findOrFail($purchaseOrderId);

        if (! $po->status->canSend()) {
            throw ValidationException::withMessages([
                'order' => 'Pesanan pembelian hanya dapat dikirim saat berstatus disetujui (approved).',
            ]);
        }

        $po->update(['status' => PurchaseOrderStatus::Sent]);

        return $po->fresh(['items']);
    }
}
