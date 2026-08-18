<?php

namespace Modules\Purchasing\Application\PurchaseQuote;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\PurchaseQuote;

class SendPurchaseQuote
{
    public function execute(int $purchaseQuoteId): PurchaseQuote
    {
        $quote = PurchaseQuote::with(['items'])->findOrFail($purchaseQuoteId);

        if ($quote->status !== 'draft') {
            throw ValidationException::withMessages([
                'quote' => 'Penawaran hanya dapat dikirim saat berstatus draft.',
            ]);
        }

        $quote->update(['status' => 'sent']);

        return $quote->fresh(['items']);
    }
}
