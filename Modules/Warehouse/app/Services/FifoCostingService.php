<?php

namespace Modules\Warehouse\Services;

use Modules\Warehouse\Models\StockLayer;

class FifoCostingService
{
    /**
     * Consume stock using FIFO (First In, First Out) logic.
     *
     * @param int $productVariantId
     * @param int $warehouseId
     * @param float $qty
     *
     * @return array<int, array{
     *     stock_layer_id: int,
     *     qty_taken: float,
     *     unit_cost: float
     * }>
     *
     * @throws InsufficientStockException
     */
    public function consume(
        int $productVariantId,
        int $warehouseId,
        float $qty
    ): array {
        if ($qty <= 0) {
            return [];
        }

        /*
         * Get available layers in FIFO order.
         *
         * received_at ASC
         * id ASC sebagai deterministic fallback.
         */
        $layers = StockLayer::query()
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /*
         * Calculate total available stock before
         * modifying any layer.
         */
        $totalAvailable = (float) $layers->sum(
            fn (StockLayer $layer) => (float) $layer->qty_remaining
        );

        /*
         * Do not partially consume stock.
         *
         * If requested quantity is greater than
         * total available quantity, nothing is consumed.
         */
        if ($totalAvailable < $qty) {
            throw new InsufficientStockException(
                sprintf(
                    'Insufficient stock for product variant %d in warehouse %d. Available: %s, Requested: %s',
                    $productVariantId,
                    $warehouseId,
                    $totalAvailable,
                    $qty
                )
            );
        }

        $remainingQty = $qty;
        $consumed = [];

        foreach ($layers as $layer) {
            if ($remainingQty <= 0) {
                break;
            }

            $availableInLayer = (float) $layer->qty_remaining;

            $take = min(
                $availableInLayer,
                $remainingQty
            );

            if ($take <= 0) {
                continue;
            }

            /*
             * Decrease the layer quantity.
             */
            $layer->decrement(
                'qty_remaining',
                $take
            );

            $consumed[] = [
                'stock_layer_id' => (int) $layer->id,
                'qty_taken' => $take,
                'unit_cost' => (float) $layer->unit_cost,
            ];

            $remainingQty -= $take;
        }

        /*
         * Safety check. This should never happen because
         * totalAvailable was already checked above.
         */
        if ($remainingQty > 0) {
            throw new InsufficientStockException(
                sprintf(
                    'Unable to consume requested stock for product variant %d in warehouse %d.',
                    $productVariantId,
                    $warehouseId
                )
            );
        }

        return $consumed;
    }
}