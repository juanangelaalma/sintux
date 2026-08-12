<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockMovement;
use Modules\Warehouse\Models\StockTransfer;
use Modules\Warehouse\Models\StockTransferItem;
use Modules\Warehouse\Services\FifoCostingService;

class ShipStockTransfer
{
    public function __construct(
        private readonly FifoCostingService $fifoCostingService,
    ) {}

    public function execute(
        int $stockTransferId,
        int $shippedById
    ): StockTransfer {
        $stockTransfer = StockTransfer::with([
            'items.productVariant',
            'fromWarehouse',
            'toWarehouse',
        ])->findOrFail($stockTransferId);

        /*
         * Hanya transfer dengan status draft
         * yang boleh dikirim.
         */
        if ($stockTransfer->status !== 'draft') {
            throw ValidationException::withMessages([
                'stock_transfer' => sprintf(
                    'Stock transfer ini sudah diproses (status: %s) dan tidak dapat dikirim ulang.',
                    $stockTransfer->status
                ),
            ]);
        }

        return DB::transaction(function () use (
            $stockTransfer,
            $shippedById
        ) {
            /*
             * Process every transfer item using FIFO.
             *
             * Jika FifoCostingService melempar
             * InsufficientStockException, exception akan
             * membatalkan transaction secara otomatis.
             */
            foreach ($stockTransfer->items as $item) {
                $this->processItem($item);
            }

            /*
             * Semua item berhasil diproses.
             * Baru ubah status menjadi shipped.
             */
            $stockTransfer->update([
                'status' => 'shipped',
                'shipped_by' => $shippedById,
                'shipped_at' => now(),
            ]);

            return $stockTransfer->load([
                'items.layers',
                'fromWarehouse',
                'toWarehouse',
            ]);
        });
    }

    private function processItem(
        StockTransferItem $item
    ): void {
        $stockTransfer = $item->stockTransfer;

        $fromWarehouseId = (int) $stockTransfer->from_warehouse_id;
        $toWarehouseId = (int) $stockTransfer->to_warehouse_id;
        $productVariantId = (int) $item->product_variant_id;
        $qtyToShip = (float) $item->qty;

        /*
         * 1. Consume stock menggunakan FIFO.
         *
         * FifoCostingService bertanggung jawab memastikan:
         *
         * - stock tersedia
         * - layer dikonsumsi berdasarkan received_at
         * - qty_remaining berkurang
         * - exception dilempar jika stock tidak mencukupi
         */
        $breakdown = $this->fifoCostingService->consume(
            $productVariantId,
            $fromWarehouseId,
            $qtyToShip
        );

        /*
         * 2. Simpan breakdown FIFO ke
         * stock_transfer_item_layers.
         */
        foreach ($breakdown as $layer) {
            $item->layers()->create([
                'stock_layer_id' => $layer['stock_layer_id'],
                'qty_taken' => $layer['qty_taken'],
                'unit_cost' => $layer['unit_cost'],
            ]);
        }

        /*
         * 3. Kurangi stock balance warehouse asal.
         */
        $updatedRows = StockBalance::query()
            ->where('warehouse_id', $fromWarehouseId)
            ->where('product_variant_id', $productVariantId)
            ->where('qty_on_hand', '>=', $qtyToShip)
            ->decrement('qty_on_hand', $qtyToShip);

        /*
         * Safety check.
         *
         * Seharusnya sudah dijamin oleh FIFO service,
         * tetapi jangan sampai balance menjadi negatif
         * jika ada race condition / data tidak konsisten.
         */
        if ($updatedRows === 0) {
            throw ValidationException::withMessages([
                'stock_transfer' => sprintf(
                    'Stok product variant %d tidak mencukupi di warehouse %d.',
                    $productVariantId,
                    $fromWarehouseId
                ),
            ]);
        }

        /*
         * 4. Tambahkan stock ke warehouse tujuan.
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
                $qtyToShip,
            ]
        );

        /*
         * 5. Record stock movement (audit trail / kartu stok) per layer konsumsi FIFO.
         */
        foreach ($breakdown as $layer) {
            StockMovement::create([
                'warehouse_id' => $fromWarehouseId,
                'product_variant_id' => $productVariantId,
                'movement_type' => 'transfer_out',
                'qty' => -abs((float) $layer['qty_taken']),
                'unit_cost' => $layer['unit_cost'],
                'stock_layer_id' => $layer['stock_layer_id'],
                'reference_type' => StockTransfer::class,
                'reference_id' => $stockTransfer->id,
            ]);
        }
    }
}
