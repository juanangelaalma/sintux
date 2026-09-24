<?php

namespace Modules\Warehouse\Application\StockLayer;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Modules\Warehouse\Models\StockLayer;

/**
 * Rantai asal satu stock layer: layer ini → layer asal (transfer) → ... →
 * layer akar. Depth dibatasi agar data korup (siklus parent) tidak
 * membebani query.
 */
class GetLayerLineage
{
    private const MAX_DEPTH = 10;

    /**
     * @return list<array{
     *     layer_id: int,
     *     warehouse_id: int,
     *     qty_remaining: float,
     *     unit_cost: float,
     *     source_type: string|null,
     *     source_id: int|null,
     *     root_source_type: string|null,
     *     root_source_id: int|null,
     *     parent_layer_id: int|null
     * }>
     */
    public function execute(int $layerId, int $depth = self::MAX_DEPTH): array
    {
        $chain = [];
        $seen = [];
        $currentId = $layerId;
        $levels = max(1, min($depth, self::MAX_DEPTH));

        for ($i = 0; $i < $levels && $currentId !== null; $i++) {
            if (isset($seen[$currentId])) {
                // Siklus parent pada data korup: berhenti, jangan loop.
                break;
            }

            $layer = StockLayer::find($currentId);

            if (! $layer) {
                throw new ModelNotFoundException("Stock layer {$currentId} tidak ditemukan.");
            }

            $seen[$currentId] = true;
            $chain[] = [
                'layer_id' => (int) $layer->id,
                'warehouse_id' => (int) $layer->warehouse_id,
                'qty_remaining' => (float) $layer->qty_remaining,
                'unit_cost' => (float) $layer->unit_cost,
                'source_type' => $layer->source_type !== null ? (string) $layer->source_type : null,
                'source_id' => $layer->source_id !== null ? (int) $layer->source_id : null,
                'root_source_type' => $layer->root_source_type !== null ? (string) $layer->root_source_type : null,
                'root_source_id' => $layer->root_source_id !== null ? (int) $layer->root_source_id : null,
                'parent_layer_id' => $layer->parent_layer_id !== null ? (int) $layer->parent_layer_id : null,
            ];

            $currentId = $layer->parent_layer_id !== null ? (int) $layer->parent_layer_id : null;
        }

        return $chain;
    }
}
