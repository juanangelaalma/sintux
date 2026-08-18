<?php

namespace Modules\Purchasing\Application\PurchaseRequest;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\PurchaseRequest;

class ApprovePurchaseRequest
{
    public function execute(int $purchaseRequestId): PurchaseRequest
    {
        $purchaseRequest = PurchaseRequest::with(['items'])
            ->findOrFail($purchaseRequestId);

        if ($purchaseRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'request' => 'Permintaan pembelian hanya dapat disetujui saat berstatus pending.',
            ]);
        }

        $purchaseRequest->update(['status' => 'approved']);

        return $purchaseRequest->fresh(['items']);
    }
}
