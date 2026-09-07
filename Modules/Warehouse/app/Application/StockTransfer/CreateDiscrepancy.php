<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Modules\Warehouse\Models\StockTransferDiscrepancy;
use Modules\Warehouse\Models\StockTransferItem;

class CreateDiscrepancy
{
    /**
     * Create a discrepancy record when received_qty != shipped_qty.
     */
    public function execute(
        StockTransferItem $item,
        float $shippedQty,
        float $receivedQty,
    ): ?StockTransferDiscrepancy {
        $difference = $shippedQty - $receivedQty;

        if (abs($difference) < 0.0001) {
            return null;
        }

        return StockTransferDiscrepancy::create([
            'stock_transfer_id' => $item->stock_transfer_id,
            'stock_transfer_item_id' => $item->id,
            'product_variant_id' => $item->product_variant_id,
            'shipped_qty' => $shippedQty,
            'received_qty' => $receivedQty,
            'difference_qty' => $difference,
            'status' => 'pending',
        ]);
    }
}
