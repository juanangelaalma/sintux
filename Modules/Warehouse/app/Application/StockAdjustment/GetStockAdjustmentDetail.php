<?php

namespace Modules\Warehouse\Application\StockAdjustment;

use Modules\Warehouse\Models\StockAdjustment;

class GetStockAdjustmentDetail
{
    public function execute(int $id): StockAdjustment
    {
        return StockAdjustment::with([
            'warehouse.branch',
            'adjustedBy',
            'items.productVariant.product',
            'items.productVariant.product.uom',
        ])->findOrFail($id);
    }
}
