<?php

namespace Modules\Purchasing\Application\PurchaseQuote;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\PurchaseQuote;

class CancelPurchaseQuote
{
    public function execute(int $purchaseQuoteId): PurchaseQuote
    {
        $quote = PurchaseQuote::with(['items'])->findOrFail($purchaseQuoteId);

        if (! in_array($quote->status, ['draft', 'sent'], true)) {
            throw ValidationException::withMessages([
                'quote' => 'Penawaran yang sudah diterima atau diproses tidak dapat dibatalkan.',
            ]);
        }

        $quote->update(['status' => 'cancelled']);

        return $quote->fresh(['items']);
    }
}
