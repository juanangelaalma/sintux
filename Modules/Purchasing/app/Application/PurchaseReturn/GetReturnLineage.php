<?php

namespace Modules\Purchasing\Application\PurchaseReturn;

use Modules\Purchasing\Models\PurchaseReturn;
use Modules\Warehouse\Application\StockLayer\GetLayerLineage;
use Modules\Warehouse\Models\StockMovement;

/**
 * Timeline rantai asal untuk satu retur pembelian: tiap baris menunjukkan
 * layer stok yang terpakai lalu rantai induknya (transfer → PO).
 *
 * Menggunaikan Application API milik Warehouse (GetLayerLineage), tidak
 * menyentuh tabel stok langsung.
 */
class GetReturnLineage
{
    public function __construct(
        private readonly GetLayerLineage $layerLineage,
    ) {}

    /**
     * @return array<int, list<array{layer_id: int, warehouse_id: int, qty_remaining: float, unit_cost: float, source_type: string|null, source_id: int|null, root_source_type: string|null, root_source_id: int|null, parent_layer_id: int|null}>>
     *                                                                                                                                                                                                                                              Map item_id => rantai layer (indeks 0 = layer yang terpakai retur).
     */
    public function execute(int $returnId): array
    {
        $return = PurchaseReturn::whereKey($returnId)->firstOrFail();

        $movements = StockMovement::query()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', $return->id)
            ->get(['id', 'product_variant_id', 'stock_layer_id']);

        $out = [];

        foreach ($return->items as $item) {
            $layerIds = $movements
                ->where('product_variant_id', (int) ($item->stock_variant_id ?? $item->product_variant_id))
                ->pluck('stock_layer_id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();

            $chain = [];

            foreach ($layerIds as $layerId) {
                foreach ($this->layerLineage->execute($layerId) as $row) {
                    $chain[] = $row;
                }
            }

            // Dedup berdasarkan layer_id, urut kemunculan (terk Consumption
            // lebih dulu di-chain paling depan).
            $seen = [];
            $deduped = [];
            foreach ($chain as $row) {
                if (! isset($seen[$row['layer_id']])) {
                    $seen[$row['layer_id']] = true;
                    $deduped[] = $row;
                }
            }

            $out[(int) $item->id] = $deduped;
        }

        return $out;
    }
}
