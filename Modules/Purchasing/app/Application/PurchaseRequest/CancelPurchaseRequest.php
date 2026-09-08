<?php

namespace Modules\Purchasing\Application\PurchaseRequest;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\PurchaseRequestStatus;
use Modules\Purchasing\Models\PurchaseRequest;

class CancelPurchaseRequest
{
    public function execute(int $purchaseRequestId): PurchaseRequest
    {
        $purchaseRequest = PurchaseRequest::with(['items'])
            ->findOrFail($purchaseRequestId);

        if (! $purchaseRequest->status->canCancel()) {
            throw ValidationException::withMessages([
                'request' => 'Permintaan pembelian yang sudah diproses tidak dapat dibatalkan.',
            ]);
        }

        $purchaseRequest->update(['status' => PurchaseRequestStatus::Cancelled]);

        return $purchaseRequest->fresh(['items']);
    }
}
