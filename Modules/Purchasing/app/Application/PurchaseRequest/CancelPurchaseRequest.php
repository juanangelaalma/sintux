<?php

namespace Modules\Purchasing\Application\PurchaseRequest;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\PurchaseRequest;

class CancelPurchaseRequest
{
    public function execute(int $purchaseRequestId): PurchaseRequest
    {
        $purchaseRequest = PurchaseRequest::with(['items'])
            ->findOrFail($purchaseRequestId);

        if (! in_array($purchaseRequest->status, ['draft', 'pending'], true)) {
            throw ValidationException::withMessages([
                'request' => 'Permintaan pembelian yang sudah diproses tidak dapat dibatalkan.',
            ]);
        }

        $purchaseRequest->update(['status' => 'cancelled']);

        return $purchaseRequest->fresh(['items']);
    }
}
