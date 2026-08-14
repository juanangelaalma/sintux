<?php

namespace Modules\Warehouse\Application\StockRequest;

use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockRequest;

class GetStockRequestDetail
{
    public function execute(int $id): StockRequest
    {
        $stockRequest = StockRequest::with([
            'requestingWarehouse.branch',
            'destinationWarehouse.branch',
            'requestedBy',
            'items.productVariant.product',
            'transfer.items.productVariant.product',
        ])->findOrFail($id);

        $hqWarehouseId = $stockRequest->destination_warehouse_id;
        $variantIds = $stockRequest->items->pluck('product_variant_id')->all();

        $stockBalances = StockBalance::where('warehouse_id', $hqWarehouseId)
            ->whereIn('product_variant_id', $variantIds)
            ->pluck('qty_on_hand', 'product_variant_id');

        foreach ($stockRequest->items as $item) {
            $item->available_qty = (float) ($stockBalances[$item->product_variant_id] ?? 0);
        }

        return $stockRequest;
    }
}
