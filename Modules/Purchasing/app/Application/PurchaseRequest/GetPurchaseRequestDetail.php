<?php

namespace Modules\Purchasing\Application\PurchaseRequest;

use Modules\Purchasing\Models\PurchaseRequest;

class GetPurchaseRequestDetail
{
    public function execute(int $id): PurchaseRequest
    {
        return PurchaseRequest::with(['items'])
            ->findOrFail($id);
    }
}
