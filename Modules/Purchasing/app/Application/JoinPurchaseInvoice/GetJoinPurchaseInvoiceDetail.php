<?php

namespace Modules\Purchasing\Application\JoinPurchaseInvoice;

use Modules\Purchasing\Models\JoinPurchaseInvoice;

class GetJoinPurchaseInvoiceDetail
{
    public function execute(int $id): JoinPurchaseInvoice
    {
        return JoinPurchaseInvoice::with(['items'])
            ->findOrFail($id);
    }
}
