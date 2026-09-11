<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Warehouse\Enums\StockTransferStatus;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockTransfer;
use Modules\Warehouse\Models\Warehouse;

class CreateDirectTransfer
{
    /**
     * Buat transfer stok langsung tanpa stock request.
     *
     * Aturan status berdasarkan gudang asal:
     * - Gudang milik cabang HQ -> draft (langsung bisa ship).
     * - Gudang milik cabang non-HQ -> pending_approval (butuh approve HO).
     *
     * @param  array{
     *     from_warehouse_id: int,
     *     to_warehouse_id: int,
     *     items: list<array{product_variant_id: int, qty: float|int}>
     * }  $data
     */
    public function execute(array $data, int $createdById): StockTransfer
    {
        $fromWarehouseId = (int) $data['from_warehouse_id'];
        $toWarehouseId = (int) $data['to_warehouse_id'];

        if ($fromWarehouseId === $toWarehouseId) {
            throw ValidationException::withMessages([
                'from_warehouse_id' => 'Gudang asal tidak boleh sama dengan gudang tujuan.',
            ]);
        }

        $fromWarehouse = Warehouse::with('branch')->find($fromWarehouseId);

        if (! $fromWarehouse) {
            throw ValidationException::withMessages([
                'from_warehouse_id' => 'Gudang asal tidak ditemukan.',
            ]);
        }

        if (! Warehouse::whereKey($toWarehouseId)->exists()) {
            throw ValidationException::withMessages([
                'to_warehouse_id' => 'Gudang tujuan tidak ditemukan.',
            ]);
        }

        $status = $fromWarehouse->branch && $fromWarehouse->branch->is_headquarters
            ? StockTransferStatus::Draft
            : StockTransferStatus::PendingApproval;

        /*
         * Transfer yang langsung draft wajib lolos cek stok saat create.
         * Transfer pending dicek saat HO approve karena stok
         * bisa berubah selama menunggu persetujuan.
         */
        if ($status === StockTransferStatus::Draft) {
            $this->assertSufficientStock($fromWarehouseId, $data['items']);
        }

        return DB::transaction(function () use ($data, $createdById, $fromWarehouseId, $toWarehouseId, $status) {
            $transfer = StockTransfer::create([
                'stock_request_id' => null,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'status' => $status->value,
                'created_by' => $createdById,
            ]);

            foreach ($data['items'] as $item) {
                $transfer->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'qty' => $item['qty'],
                ]);
            }

            return $transfer->load(['items', 'fromWarehouse', 'toWarehouse']);
        });
    }

    /**
     * @param  list<array{product_variant_id: int, qty: float|int}>  $items
     */
    private function assertSufficientStock(int $warehouseId, array $items): void
    {
        $balances = StockBalance::where('warehouse_id', $warehouseId)
            ->whereIn('product_variant_id', collect($items)->pluck('product_variant_id')->all())
            ->pluck('qty_on_hand', 'product_variant_id');

        foreach ($items as $item) {
            $available = (float) ($balances[$item['product_variant_id']] ?? 0);

            if ((float) $item['qty'] > $available) {
                throw ValidationException::withMessages([
                    'items' => "Stok varian {$item['product_variant_id']} tidak mencukupi di gudang asal (tersedia: {$available}).",
                ]);
            }
        }
    }
}
