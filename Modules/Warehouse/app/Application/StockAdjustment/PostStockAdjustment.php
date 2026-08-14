<?php

namespace Modules\Warehouse\Application\StockAdjustment;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Warehouse\Models\StockAdjustment;
use Modules\Warehouse\Models\StockAdjustmentItem;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockLayer;
use Modules\Warehouse\Models\StockMovement;
use Modules\Warehouse\Services\FifoCostingService;

class PostStockAdjustment
{
    public function __construct(
        private readonly FifoCostingService $fifoCostingService,
    ) {}

    public function execute(int $adjustmentId, int $userId): StockAdjustment
    {
        $adjustment = StockAdjustment::with(['items.productVariant', 'warehouse'])
            ->findOrFail($adjustmentId);

        if ($adjustment->status !== 'draft') {
            throw ValidationException::withMessages([
                'stock_adjustment' => sprintf(
                    'Stock adjustment ini sudah diproses (status: %s) dan tidak dapat diposting ulang.',
                    $adjustment->status
                ),
            ]);
        }

        return DB::transaction(function () use ($adjustment, $userId) {
            if ($adjustment->type === 'in') {
                foreach ($adjustment->items as $item) {
                    $this->processAdjustmentIn($adjustment, $item);
                }
            } else {
                foreach ($adjustment->items as $item) {
                    $this->processAdjustmentOut($adjustment, $item);
                }
            }

            $adjustment->update([
                'status' => 'posted',
                'adjusted_by' => $userId,
                'adjusted_at' => now(),
            ]);

            return $adjustment->load(['warehouse', 'items.productVariant.product', 'adjustedBy']);
        });
    }

    private function processAdjustmentIn(StockAdjustment $adjustment, StockAdjustmentItem $item): void
    {
        $warehouseId = (int) $adjustment->warehouse_id;
        $productVariantId = (int) $item->product_variant_id;
        $qty = (float) $item->qty;
        $unitCost = (float) ($item->unit_cost ?? 0);

        // 1. Create Stock Layer
        $layer = StockLayer::create([
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'qty_remaining' => $qty,
            'unit_cost' => $unitCost,
            'received_at' => now(),
            'source_type' => 'adjustment',
            'source_id' => $adjustment->id,
        ]);

        // 2. Upsert Stock Balance
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
                qty_on_hand = stock_balances.qty_on_hand + EXCLUDED.qty_on_hand,
                updated_at = NOW()
            SQL,
            [
                $warehouseId,
                $productVariantId,
                $qty,
            ]
        );

        // 3. Record Stock Movement
        StockMovement::create([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $productVariantId,
            'movement_type' => 'adjustment_in',
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'stock_layer_id' => $layer->id,
            'reference_type' => StockAdjustment::class,
            'reference_id' => $adjustment->id,
        ]);
    }

    private function processAdjustmentOut(StockAdjustment $adjustment, StockAdjustmentItem $item): void
    {
        $warehouseId = (int) $adjustment->warehouse_id;
        $productVariantId = (int) $item->product_variant_id;
        $qtyToConsume = (float) $item->qty;

        // 1. Consume stock using FIFO
        $breakdown = $this->fifoCostingService->consume(
            $productVariantId,
            $warehouseId,
            $qtyToConsume
        );

        // 2. Decrement Stock Balance
        $updatedRows = StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $productVariantId)
            ->where('qty_on_hand', '>=', $qtyToConsume)
            ->decrement('qty_on_hand', $qtyToConsume);

        if ($updatedRows === 0) {
            throw ValidationException::withMessages([
                'stock_adjustment' => sprintf(
                    'Stok variant %d tidak mencukupi di warehouse %d.',
                    $productVariantId,
                    $warehouseId
                ),
            ]);
        }

        // 3. Record Stock Movement for each consumed layer
        foreach ($breakdown as $layer) {
            StockMovement::create([
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $productVariantId,
                'movement_type' => 'adjustment_out',
                'qty' => -abs((float) $layer['qty_taken']),
                'unit_cost' => $layer['unit_cost'],
                'stock_layer_id' => $layer['stock_layer_id'],
                'reference_type' => StockAdjustment::class,
                'reference_id' => $adjustment->id,
            ]);
        }
    }
}
