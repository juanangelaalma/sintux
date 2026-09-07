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
    public function __construct(
        private readonly CreateDiscrepancy $createDiscrepancy,
    ) {}

    /**
     * @param  array<int, array{stock_transfer_item_id: int, qty_received: float}>  $receivedItems
     */
    public function execute(
        int $stockTransferId,
        array $receivedItems,
        int $receivedById
    ): StockTransfer {
        $stockTransfer = StockTransfer::with([
            'items.productVariant',
            'items.layers.stockLayer',
            'fromWarehouse',
            'toWarehouse',
        ])->findOrFail($stockTransferId);

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
            $receivedItems,
            $receivedById
        ) {
            foreach ($receivedItems as $received) {
                $item = $stockTransfer->items->first(
                    fn (StockTransferItem $i) => (int) $i->id === (int) $received['stock_transfer_item_id']
                );

                if (! $item) {
                    throw ValidationException::withMessages([
                        'stock_transfer_item' => sprintf(
                            'Item dengan ID %d tidak ditemukan dalam transfer ini.',
                            $received['stock_transfer_item_id']
                        ),
                    ]);
                }

                $this->processItem($item, (float) $received['qty_received']);
            }

            /*
             * Check apakah semua item sudah diterima sepenuhnya.
             */
            $allFullyReceived = $stockTransfer->items->every(
                fn (StockTransferItem $item) => abs((float) $item->qty_received - (float) $item->qty_shipped) < 0.0001
            );

            $stockTransfer->update([
                'status' => $allFullyReceived ? 'received' : 'shipped',
                'received_by' => $receivedById,
                'received_at' => now(),
            ]);

            return $stockTransfer->load([
                'items.layers.stockLayer',
                'items.discrepancies',
                'fromWarehouse',
                'toWarehouse',
            ]);
        });
    }

    private function processItem(
        StockTransferItem $item,
        float $qtyReceived,
    ): void {
        $stockTransfer = $item->stockTransfer;

        $toWarehouseId = (int) $stockTransfer->to_warehouse_id;
        $productVariantId = (int) $item->product_variant_id;

        $qtyShipped = (float) $item->qty_shipped;
        $qtyPreviouslyReceived = (float) $item->qty_received;
        $remainingQty = $qtyShipped - $qtyPreviouslyReceived;

        if ($qtyReceived > $remainingQty + 0.0001) {
            throw ValidationException::withMessages([
                'qty_received' => sprintf(
                    'Qty received (%s) melebihi sisa qty yang belum diterima (%s) untuk item %d.',
                    $qtyReceived,
                    $remainingQty,
                    $item->id
                ),
            ]);
        }

        /*
         * Distribute received qty across FIFO layers.
         * Layer diambil dari stock_transfer_item_layers (breakdown saat ship).
         */
        $breakdownLayers = $item->layers()->with('stockLayer')->get();
        $qtyToAllocate = $qtyReceived;

        foreach ($breakdownLayers as $breakdownLayer) {
            if ($qtyToAllocate <= 0) {
                break;
            }

            $layerQtyAvailable = (float) $breakdownLayer->qty_taken;
            $allocateQty = min($layerQtyAvailable, $qtyToAllocate);

            if ($allocateQty <= 0) {
                continue;
            }

            /*
             * Create StockLayer di warehouse tujuan.
             */
            $newLayer = StockLayer::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $toWarehouseId,
                'qty_remaining' => $allocateQty,
                'unit_cost' => (float) $breakdownLayer->unit_cost,
                'received_at' => now(),
                'source_type' => 'stock_transfer',
                'source_id' => $stockTransfer->id,
            ]);

            /*
             * Increment stock balance warehouse tujuan.
             */
            DB::statement(
                <<<'SQL'
                INSERT INTO stock_balances (
                    warehouse_id,
                    product_variant_id,
                    qty_on_hand,
                    created_at,
                    updated_at
                )
                VALUES (?, ?, ?, NOW(), NOW())
                ON CONFLICT (warehouse_id, product_variant_id)
                DO UPDATE SET
                    qty_on_hand =
                        stock_balances.qty_on_hand + EXCLUDED.qty_on_hand,
                    updated_at = NOW()
                SQL,
                [
                    $toWarehouseId,
                    $productVariantId,
                    $allocateQty,
                ]
            );

            /*
             * Record stock movement (audit trail / kartu stok).
             */
            StockMovement::create([
                'warehouse_id' => $toWarehouseId,
                'product_variant_id' => $productVariantId,
                'movement_type' => 'transfer_in',
                'qty' => $allocateQty,
                'unit_cost' => (float) $breakdownLayer->unit_cost,
                'stock_layer_id' => $newLayer->id,
                'reference_type' => StockTransfer::class,
                'reference_id' => $stockTransfer->id,
            ]);

            $qtyToAllocate -= $allocateQty;
        }

        /*
         * Update qty_received pada item.
         */
        $item->update([
            'qty_received' => $qtyPreviouslyReceived + $qtyReceived,
        ]);

        /*
         * Create discrepancy jika received != shipped.
         */
        $this->createDiscrepancy->execute(
            $item,
            $qtyShipped,
            $qtyPreviouslyReceived + $qtyReceived,
        );
    }
}
