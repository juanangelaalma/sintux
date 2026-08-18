<?php

namespace Modules\Warehouse\Application\StockLayer;

use Illuminate\Support\Facades\DB;
use Modules\Warehouse\Models\StockLayer;
use Modules\Warehouse\Models\StockMovement;

class ReceivePurchaseStock
{
    /**
     * Receive purchased goods into a warehouse.
     *
     * Public cross-module API owned by the Warehouse module. Consuming
     * modules (e.g. Purchasing) post stock through this entry point
     * instead of touching stock tables directly.
     *
     * @param  array{
     *     warehouse_id: int,
     *     product_variant_id: int,
     *     qty: float,
     *     unit_cost: float,
     *     received_at: \DateTimeInterface|string,
     *     source_type: string,
     *     source_id: int,
     *     reference_type?: string|null
     * }  $data
     */
    public function execute(array $data): void
    {
        $warehouseId = (int) $data['warehouse_id'];
        $productVariantId = (int) $data['product_variant_id'];
        $qty = (float) $data['qty'];
        $unitCost = (float) $data['unit_cost'];
        $sourceType = $data['source_type'];
        $sourceId = (int) $data['source_id'];

        DB::transaction(function () use (
            $warehouseId,
            $productVariantId,
            $qty,
            $unitCost,
            $sourceType,
            $sourceId,
            $data
        ): void {
            // 1. Create Stock Layer
            $layer = StockLayer::create([
                'product_variant_id' => $productVariantId,
                'warehouse_id' => $warehouseId,
                'qty_remaining' => $qty,
                'unit_cost' => $unitCost,
                'received_at' => $data['received_at'],
                'source_type' => $sourceType,
                'source_id' => $sourceId,
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
                'movement_type' => 'purchase_in',
                'qty' => $qty,
                'unit_cost' => $unitCost,
                'stock_layer_id' => $layer->id,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $sourceId,
            ]);
        });
    }
}
