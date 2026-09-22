<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Warehouse\Application\StockTransfer\CreateDirectTransfer;
use Modules\Warehouse\Application\StockTransfer\ReceiveStockTransfer;
use Modules\Warehouse\Application\StockTransfer\ShipStockTransfer;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;

/**
 * Transfer synchronous HO→cabang seusai approval.
 *
 * ponytail: bila GRN membawa ratusan bundle sekaligus dan approval
 * terasa lambat, pindahkan kembali ke queue (dispatch job) + tampilkan
 * status antre di inbox. Sampai saat itu sync adalah yang benar.
 */
class TransferApprovedGrn
{
    public function __construct(
        private readonly CreateDirectTransfer $createTransfer,
        private readonly ShipStockTransfer $shipTransfer,
        private readonly ReceiveStockTransfer $receiveTransfer,
        private readonly GetWarehouses $warehouses,
    ) {}

    public function execute(int $goodsReceiptId, int $userId): void
    {
        try {
            DB::transaction(function () use ($goodsReceiptId, $userId) {
                $grn = GoodsReceipt::with(['items'])
                    ->lockForUpdate()
                    ->findOrFail($goodsReceiptId);

                // Idempoten: hanya jalan sekali untuk GRN yang approved.
                if ($grn->status !== GoodsReceiptStatus::Approved || $grn->transferred_at) {
                    return;
                }

                $fromWarehouseId = (int) $grn->warehouse_id;

                $groups = [];
                foreach ($grn->items as $item) {
                    if ((float) $item->qty_received <= 0) {
                        continue;
                    }

                    $toWarehouseId = $this->resolveDestination($grn, $item);

                    // Tujuan sama dengan asal (mis. GRN cabang HO sendiri):
                    // stok sudah di tempat, transfer dilewati.
                    if ($toWarehouseId === $fromWarehouseId) {
                        continue;
                    }

                    $groups[$toWarehouseId][] = [
                        'product_variant_id' => (int) $item->product_variant_id,
                        'qty' => (float) $item->qty_received,
                    ];
                }

                foreach ($groups as $toWarehouseId => $items) {
                    $transfer = $this->createTransfer->execute([
                        'from_warehouse_id' => $fromWarehouseId,
                        'to_warehouse_id' => (int) $toWarehouseId,
                        'items' => array_map(
                            fn ($i) => ['product_variant_id' => $i['product_variant_id'], 'qty' => $i['qty']],
                            $items
                        ),
                        'source_type' => GoodsReceipt::class,
                        'source_id' => $grn->id,
                    ], $userId);

                    $shipped = $this->shipTransfer->execute($transfer->id, $userId);

                    $this->receiveTransfer->execute($shipped->id, $shipped->items->map(
                        fn ($transferItem) => [
                            'stock_transfer_item_id' => (int) $transferItem->id,
                            'qty_received' => (float) $transferItem->qty,
                        ]
                    )->all(), $userId);
                }

                $grn->update(['transferred_at' => now(), 'transfer_error' => null]);
            });
        } catch (\Throwable $exception) {
            // Stok tetap aman di HO; catat penyebab agar terlihat di inbox.
            try {
                GoodsReceipt::where('id', $goodsReceiptId)
                    ->update(['transfer_error' => mb_substr($exception->getMessage(), 0, 500)]);
            } catch (\Throwable $recordException) {
                Log::warning('Gagal mencatat transfer_error GRN', [
                    'goods_receipt_id' => $goodsReceiptId,
                    'record_error' => $recordException->getMessage(),
                ]);
            }

            throw $exception;
        }
    }

    private function resolveDestination(GoodsReceipt $grn, $item): int
    {
        if ($item->purchase_order_item_id) {
            $destination = DB::table('purchase_order_items')
                ->where('id', $item->purchase_order_item_id)
                ->value('destination_warehouse_id');

            if ($destination) {
                return (int) $destination;
            }
        }

        $fallback = $this->warehouses->defaultRegularWarehouseId((int) $grn->branch_id);

        if ($fallback === null) {
            throw ValidationException::withMessages([
                'grn' => 'Gudang Regular cabang tujuan tidak ditemukan.',
            ]);
        }

        return $fallback;
    }
}
