<?php

namespace Modules\Warehouse\Application\StockRequest;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Warehouse\Models\StockRequest;
use Modules\Warehouse\Models\Warehouse;

class CreateStockRequest
{
    /**
     * Create a new stock request.
     *
     * @param  array{
     *     requesting_warehouse_id: int,
     *     destination_warehouse_id: int,
     *     note?: string|null,
     *     items: list<array{product_variant_id: int, qty_requested: float|int}>
     * }  $data
     */
    public function execute(array $data, int $requestedById): StockRequest
    {
        $requestingWarehouseId = (int) $data['requesting_warehouse_id'];
        $destinationWarehouseId = (int) $data['destination_warehouse_id'];

        if ($requestingWarehouseId === $destinationWarehouseId) {
            throw ValidationException::withMessages([
                'requesting_warehouse_id' => 'Gudang peminta tidak boleh sama dengan gudang tujuan.',
            ]);
        }

        $destinationWarehouse = Warehouse::with('branch')->find($destinationWarehouseId);

        if (! $destinationWarehouse || ! $destinationWarehouse->branch || ! $destinationWarehouse->branch->is_headquarters) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => 'Gudang tujuan harus milik Cabang Utama (HQ).',
            ]);
        }

        return DB::transaction(function () use ($data, $requestedById, $requestingWarehouseId, $destinationWarehouseId) {
            $stockRequest = StockRequest::create([
                'requesting_warehouse_id' => $requestingWarehouseId,
                'destination_warehouse_id' => $destinationWarehouseId,
                'requested_by' => $requestedById,
                'status' => 'pending',
                'note' => $data['note'] ?? null,
                'requested_at' => now(),
            ]);

            foreach ($data['items'] as $item) {
                $stockRequest->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'qty_requested' => $item['qty_requested'],
                ]);
            }

            return $stockRequest->load(['items', 'requestingWarehouse', 'destinationWarehouse']);
        });
    }
}
