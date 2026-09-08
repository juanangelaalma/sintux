<?php

namespace Modules\Purchasing\Application\PurchaseQuote;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\PurchaseQuoteStatus;
use Modules\Purchasing\Models\PurchaseQuote;

class AcceptPurchaseQuote
{
    public function execute(int $purchaseQuoteId): PurchaseQuote
    {
        $quote = PurchaseQuote::with(['items'])->findOrFail($purchaseQuoteId);

        if (! $quote->status->canAccept()) {
            throw ValidationException::withMessages([
                'quote' => 'Penawaran hanya dapat diterima saat berstatus dikirim (sent).',
            ]);
        }

        $quote->update(['status' => PurchaseQuoteStatus::Accepted]);

        return $quote->fresh(['items']);
    }
}
