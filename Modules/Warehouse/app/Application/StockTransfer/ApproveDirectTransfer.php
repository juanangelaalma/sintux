<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Illuminate\Validation\ValidationException;
use Modules\Warehouse\Enums\StockTransferStatus;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockTransfer;

class ApproveDirectTransfer
{
    /**
     * HO menyetujui atau menolak transfer pending dari branch non-HO.
     *
     * - approve -> status draft (siap ship), wajib lolos cek stok sumber.
     * - reject -> status rejected.
     */
    public function execute(int $stockTransferId, string $decision, int $approvedById): StockTransfer
    {
        $stockTransfer = StockTransfer::with(['items', 'fromWarehouse'])->findOrFail($stockTransferId);

        if ($stockTransfer->status !== StockTransferStatus::PendingApproval->value) {
            throw ValidationException::withMessages([
                'stock_transfer' => sprintf(
                    'Hanya transfer menunggu persetujuan HO yang dapat diproses (status: %s).',
                    $stockTransfer->status
                ),
            ]);
        }

        if ($decision === 'reject') {
            $stockTransfer->update([
                'status' => StockTransferStatus::Rejected->value,
                'approved_by' => $approvedById,
                'approved_at' => now(),
            ]);

            return $stockTransfer->load(['items', 'fromWarehouse', 'toWarehouse']);
        }

        $this->assertSufficientStock(
            (int) $stockTransfer->from_warehouse_id,
            $stockTransfer->items->map(
                fn ($item) => [
                    'product_variant_id' => (int) $item->product_variant_id,
                    'qty' => (float) $item->qty,
                ]
            )->all()
        );

        $stockTransfer->update([
            'status' => StockTransferStatus::Draft->value,
            'approved_by' => $approvedById,
            'approved_at' => now(),
        ]);

        return $stockTransfer->load(['items', 'fromWarehouse', 'toWarehouse']);
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
