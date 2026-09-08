<?php

namespace Modules\Purchasing\Application\JoinPurchaseInvoice;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\JoinPurchaseInvoiceStatus;
use Modules\Purchasing\Models\JoinPurchaseInvoice;

class ReadyJoinPurchaseInvoice
{
    public function execute(int $joinPurchaseInvoiceId): JoinPurchaseInvoice
    {
        $join = JoinPurchaseInvoice::with(['items'])->findOrFail($joinPurchaseInvoiceId);

        if (! $join->status->canMarkReady()) {
            throw ValidationException::withMessages([
                'join' => 'Tukar faktur hanya dapat diubah ke status Siap (ready) dari draft.',
            ]);
        }

        $join->update(['status' => JoinPurchaseInvoiceStatus::Ready]);

        return $join->fresh(['items']);
    }
}
