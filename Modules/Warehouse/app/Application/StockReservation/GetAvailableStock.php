<?php

namespace Modules\Warehouse\Application\StockReservation;

use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockLayer;
use Modules\Warehouse\Models\StockReservation;

/**
 * Available stock = on-hand balance minus reserved quantities.
 *
 * Public cross-module API owned by the Warehouse module. Consuming
 * modules (e.g. Sales) check availability through this entry point
 * instead of touching stock tables directly.
 */
class GetAvailableStock
{
    public function forItem(int $warehouseId, int $variantId): int
    {
        return $this->forItems($warehouseId, [$variantId])[$variantId] ?? 0;
    }

    /**
     * @param  list<int>  $variantIds
     * @return array<int, int> Variant id => available qty.
     */
    public function forItems(int $warehouseId, array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $onHand = StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_variant_id', $variantIds)
            ->pluck('qty_on_hand', 'product_variant_id');

        $reserved = StockReservation::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_variant_id', $variantIds)
            ->selectRaw('product_variant_id, SUM(qty) as total_qty')
            ->groupBy('product_variant_id')
            ->pluck('total_qty', 'product_variant_id');

        $available = [];

        foreach ($variantIds as $variantId) {
            $available[$variantId] = (int) ($onHand[$variantId] ?? 0) - (int) ($reserved[$variantId] ?? 0);
        }

        return $available;
    }

    /**
     * Stok dari satu transfer retur saja (provenance): jumlah sisa layer
     * transfer itu per varian. Reservasi tidak terlacak per sumber sehingga
     * sengaja diabaikan di sini; guard atomik ada di consume saat finalize.
     *
     * @param  list<int>  $variantIds
     * @return array<int, int> Variant id => qty dari transfer.
     */
    public function forItemsFromTransfer(int $warehouseId, array $variantIds, int $transferId): array
    {
        if ($variantIds === []) {
            return [];
        }

        $fromTransfer = StockLayer::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_variant_id', $variantIds)
            ->where('source_type', 'stock_transfer')
            ->where('source_id', $transferId)
            ->selectRaw('product_variant_id, SUM(qty_remaining) as total_qty')
            ->groupBy('product_variant_id')
            ->pluck('total_qty', 'product_variant_id');

        $available = [];

        foreach ($variantIds as $variantId) {
            $available[$variantId] = (int) ($fromTransfer[$variantId] ?? 0);
        }

        return $available;
    }
}
