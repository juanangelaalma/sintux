<?php

namespace Modules\Purchasing\Application\PurchaseQuote;

use Modules\Purchasing\Models\PurchaseQuote;

class GetPurchaseQuoteDetail
{
    public function execute(int $id): PurchaseQuote
    {
        return PurchaseQuote::with(['items'])
            ->findOrFail($id);
    }
}
