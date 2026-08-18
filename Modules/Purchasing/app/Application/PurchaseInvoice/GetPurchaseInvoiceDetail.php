<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Modules\Purchasing\Models\PurchaseInvoice;

class GetPurchaseInvoiceDetail
{
    public function execute(int $id): PurchaseInvoice
    {
        return PurchaseInvoice::with(['items'])
            ->findOrFail($id);
    }
}
