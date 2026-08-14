<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Warehouse\Models\StockLayer;
use Modules\Warehouse\Models\StockMovement;
use Modules\Warehouse\Models\StockTransfer;
use Modules\Warehouse\Models\StockTransferItem;

class ReceiveStockTransfer
{
    public function execute(
        int $stockTransferId,
        int $receivedById
    ): StockTransfer {
        $stockTransfer = StockTransfer::with([
            'items.layers',
            'fromWarehouse',
            'toWarehouse',
        ])->findOrFail($stockTransferId);

        /*
         * Hanya transfer dengan status shipped
         * yang boleh diterima.
         */
        if ($stockTransfer->status !== 'shipped') {
            throw ValidationException::withMessages([
                'stock_transfer' => sprintf(
                    'Stock transfer ini tidak dalam status shipped (status: %s) dan tidak dapat diterima.',
                    $stockTransfer->status
                ),
            ]);
        }

        return DB::transaction(function () use (
            $stockTransfer,
            $receivedById
        ) {
            foreach ($stockTransfer->items as $item) {
                $this->processItem($item);
            }

            $stockTransfer->update([
                'status' => 'received',
                'received_by' => $receivedById,
                'received_at' => now(),
            ]);

            return $stockTransfer->load([
                'items.layers.stockLayer',
                'fromWarehouse',
                'toWarehouse',
            ]);
        });
    }

    private function processItem(
        StockTransferItem $item
    ): void {
        $stockTransfer = $item->stockTransfer;

        $toWarehouseId = (int) $stockTransfer->to_warehouse_id;
        $productVariantId = (int) $item->product_variant_id;

        /*
         * Setiap layer yang dikonsumsi saat pengiriman
         * menjadi layer baru di gudang tujuan.
         *
         * unit_cost dipertahankan dari breakdown FIFO
         * agar biaya masuk gudang tujuan konsisten dengan
         * biaya saat stok keluar dari gudang asal.
         */
        foreach ($item->layers as $breakdownLayer) {
            $newLayer = StockLayer::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $toWarehouseId,
                'qty_remaining' => $breakdownLayer->qty_taken,
                'unit_cost' => $breakdownLayer->unit_cost,
                'received_at' => now(),
                'source_type' => 'stock_transfer',
                'source_id' => $stockTransfer->id,
            ]);

            StockMovement::create([
                'warehouse_id' => $toWarehouseId,
                'product_variant_id' => $productVariantId,
                'movement_type' => 'transfer_in',
                'qty' => (float) $breakdownLayer->qty_taken,
                'unit_cost' => $breakdownLayer->unit_cost,
                'stock_layer_id' => $newLayer->id,
                'reference_type' => StockTransfer::class,
                'reference_id' => $stockTransfer->id,
            ]);
        }
    }
}
