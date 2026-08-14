<?php

namespace Modules\Warehouse\Application\StockLayer;

use Illuminate\Support\Collection;
use Modules\Warehouse\Models\StockLayer;

class GetStockLayers
{
    /**
     * Get active FIFO layers for a specific product variant and warehouse.
     *
     * @return Collection<int, StockLayer>
     */
    public function execute(int $warehouseId, int $productVariantId): Collection
    {
        return StockLayer::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $productVariantId)
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->get();
    }
}
