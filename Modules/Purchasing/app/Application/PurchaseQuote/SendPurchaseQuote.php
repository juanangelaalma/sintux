<?php

namespace Modules\Purchasing\Application\PurchaseQuote;

use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\PurchaseQuoteStatus;
use Modules\Purchasing\Models\PurchaseQuote;

class SendPurchaseQuote
{
    public function execute(int $purchaseQuoteId): PurchaseQuote
    {
        $quote = PurchaseQuote::with(['items'])->findOrFail($purchaseQuoteId);

        if (! $quote->status->canSend()) {
            throw ValidationException::withMessages([
                'quote' => 'Penawaran hanya dapat dikirim saat berstatus draft.',
            ]);
        }

        $quote->update(['status' => PurchaseQuoteStatus::Sent]);

        return $quote->fresh(['items']);
    }
}
