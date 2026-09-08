<?php

namespace Modules\Purchasing\Application\PurchaseQuote;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\PurchaseQuoteStatus;
use Modules\Purchasing\Models\PurchaseQuote;

class CancelPurchaseQuote
{
    public function execute(int $purchaseQuoteId): PurchaseQuote
    {
        $quote = PurchaseQuote::with(['items'])->findOrFail($purchaseQuoteId);

        if (! $quote->status->canCancel()) {
            throw ValidationException::withMessages([
                'quote' => 'Penawaran yang sudah diterima atau diproses tidak dapat dibatalkan.',
            ]);
        }

        $quote->update(['status' => PurchaseQuoteStatus::Cancelled]);

        return $quote->fresh(['items']);
    }
}
