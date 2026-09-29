<?php

namespace Modules\Warehouse\Application\StockIssue;

use Illuminate\Support\Facades\DB;
use Modules\Warehouse\Application\StockReservation\GetAvailableStock;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockMovement;
use Modules\Warehouse\Services\FifoCostingService;
use Modules\Warehouse\Services\InsufficientStockException;

class IssueReturnStock
{
    public function __construct(
        private readonly FifoCostingService $fifoCostingService,
        private readonly GetAvailableStock $availableStock,
    ) {}

    /**
     * Keluarkan stok retur pembelian dari gudang (FIFO).
     *
     * Public cross-module API milik Warehouse. Konsumen (Purchasing)
     * memanggil entry point ini, bukan tabel stok langsung.
     *
     * Idempoten per referensi: pemanggilan ulang dengan
     * reference_type + reference_id + variant + warehouse yang sama
     * mengembalikan biaya sebelumnya tanpa consume ganda.
     *
     * Bila source_transfer_id diisi (provenance retur cabang → HO), hanya
     * layer dari transfer itu yang boleh di-consume; stok lain di gudang
     * yang sama diabaikan.
     *
     * @param  array{warehouse_id: int, product_variant_id: int, qty: int, reference_type: string, reference_id: int, source_transfer_id?: int|null}  $data
     * @return array{qty: int, total_cost: float, layers: list<array{stock_layer_id: int, qty_taken: float, unit_cost: float}>}
     *
     * @throws InsufficientStockException
     */
    public function execute(array $data): array
    {
        $warehouseId = (int) $data['warehouse_id'];
        $variantId = (int) $data['product_variant_id'];
        $qty = (int) $data['qty'];
        $referenceType = (string) $data['reference_type'];
        $referenceId = (int) $data['reference_id'];
        $sourceTransferId = isset($data['source_transfer_id']) ? (int) $data['source_transfer_id'] : null;

        if ($qty <= 0) {
            throw new InsufficientStockException('Qty retur harus lebih dari 0.');
        }

        return DB::transaction(function () use ($warehouseId, $variantId, $qty, $referenceType, $referenceId, $sourceTransferId): array {
            $existing = StockMovement::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($existing->isNotEmpty()) {
                $layers = $existing->map(fn (StockMovement $movement): array => [
                    'stock_layer_id' => (int) $movement->stock_layer_id,
                    'qty_taken' => abs((float) $movement->qty),
                    'unit_cost' => (float) $movement->unit_cost,
                ])->all();

                $totalCost = 0.0;
                foreach ($layers as $layer) {
                    $totalCost += $layer['qty_taken'] * $layer['unit_cost'];
                }

                return [
                    'qty' => (int) array_sum(array_column($layers, 'qty_taken')),
                    'total_cost' => $totalCost,
                    'layers' => $layers,
                ];
            }

            if ($sourceTransferId !== null) {
                $available = $this->availableStock->forItemsFromTransfer($warehouseId, [$variantId], $sourceTransferId)[$variantId] ?? 0;

                if ($available < $qty) {
                    throw new InsufficientStockException(
                        sprintf(
                            'Stok transfer #%d untuk product variant %d tidak mencukupi di gudang %d (tersedia: %d, diminta: %d).',
                            $sourceTransferId,
                            $variantId,
                            $warehouseId,
                            $available,
                            $qty
                        )
                    );
                }
            } else {
                $available = $this->availableStock->forItem($warehouseId, $variantId);

                if ($available < $qty) {
                    throw new InsufficientStockException(
                        sprintf(
                            'Stok product variant %d tidak mencukupi di gudang %d (tersedia: %d, diminta: %d).',
                            $variantId,
                            $warehouseId,
                            $available,
                            $qty
                        )
                    );
                }
            }

            $breakdown = $this->fifoCostingService->consume($variantId, $warehouseId, (float) $qty, $sourceTransferId);

            $updatedRows = StockBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)
                ->where('qty_on_hand', '>=', $qty)
                ->lockForUpdate()
                ->decrement('qty_on_hand', $qty);

            if ($updatedRows === 0) {
                throw new InsufficientStockException(
                    sprintf(
                        'Stok product variant %d tidak mencukupi di gudang %d.',
                        $variantId,
                        $warehouseId
                    )
                );
            }

            foreach ($breakdown as $layer) {
                StockMovement::create([
                    'warehouse_id' => $warehouseId,
                    'product_variant_id' => $variantId,
                    'movement_type' => 'purchase_return_out',
                    'qty' => -abs((float) $layer['qty_taken']),
                    'unit_cost' => $layer['unit_cost'],
                    'stock_layer_id' => $layer['stock_layer_id'],
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                ]);
            }

            $totalCost = 0.0;
            foreach ($breakdown as $layer) {
                $totalCost += (float) $layer['qty_taken'] * (float) $layer['unit_cost'];
            }

            return [
                'qty' => $qty,
                'total_cost' => $totalCost,
                'layers' => $breakdown,
            ];
        });
    }
}
