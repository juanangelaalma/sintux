<?php

namespace Modules\Purchasing\Application\JoinPurchaseInvoice;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\JoinPurchaseInvoice;

class ReadyJoinPurchaseInvoice
{
    public function execute(int $joinPurchaseInvoiceId): JoinPurchaseInvoice
    {
        $join = JoinPurchaseInvoice::with(['items'])->findOrFail($joinPurchaseInvoiceId);

        if ($join->status !== 'draft') {
            throw ValidationException::withMessages([
                'join' => 'Tukar faktur hanya dapat diubah ke status Siap (ready) dari draft.',
            ]);
        }

        $join->update(['status' => 'ready']);

        return $join->fresh(['items']);
    }
}
