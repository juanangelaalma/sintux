<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Modules\Warehouse\Models\StockTransfer;

class GetStockTransferDetail
{
    public function execute(int $id): StockTransfer
    {
        return StockTransfer::with([
            'fromWarehouse.branch',
            'toWarehouse.branch',
            'shippedBy',
            'receivedBy',
            'items.productVariant.product.uom',
            'items.layers.stockLayer.warehouse',
            'items.discrepancies',
        ])->findOrFail($id);
    }
}
