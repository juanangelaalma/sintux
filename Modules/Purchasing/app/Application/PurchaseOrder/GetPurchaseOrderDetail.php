<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Modules\Purchasing\Models\PurchaseOrder;

class GetPurchaseOrderDetail
{
    public function execute(int $id): PurchaseOrder
    {
        return PurchaseOrder::with(['items.destinationBranch', 'items.destinationWarehouse', 'tags'])
            ->findOrFail($id);
    }
}
