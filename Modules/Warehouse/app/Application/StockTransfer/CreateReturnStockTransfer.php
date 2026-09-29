<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Product\Application\Variant\GetVariantForBranch;
use Modules\Warehouse\Application\StockReservation\GetAvailableStock;
use Modules\Warehouse\Enums\StockTransferStatus;
use Modules\Warehouse\Models\StockTransfer;
use Modules\Warehouse\Models\StockTransferItem;

/**
 * Retur transfer stok (RTRF): cabang mengirim barang kembali ke HQ
 * berdasarkan transfer outbound yang sudah diterima.
 *
 * RTRF tidak memilih faktur. Provenance diambil dari transfer outbound:
 * source_type/source_id diwarisi apa adanya supaya rantai
 * PO -> GRN -> TRF -> RTRF tetap utuh saat HQ menerima barang kembali.
 * Pemisahan per faktur ditegakkan saat HQ membuat retur pembelian,
 * bukan di sini.
 *
 * Selalu masuk approval: pengembalian branch ke HQ memindahkan stok
 * milik cabang ke gudang pusat.
 */
class CreateReturnStockTransfer
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
        private readonly GetVariantForBranch $returnVariants,
        private readonly GetAvailableStock $availableStock,
    ) {}

    /**
     * @param  array{
     *     origin_transfer_id: int,
     *     items: list<array{stock_transfer_item_id: int, qty: int}>
     * }  $data
     */
    public function execute(array $data, int $createdById, ?string $createdByName = null): StockTransfer
    {
        $originId = (int) $data['origin_transfer_id'];
        $items = $data['items'] ?? [];

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Pilih minimal satu barang yang akan dikembalikan.',
            ]);
        }

        $origin = StockTransfer::query()
            ->with(['fromWarehouse.branch', 'toWarehouse.branch', 'items'])
            ->find($originId);

        if (! $origin) {
            throw ValidationException::withMessages([
                'origin_transfer_id' => 'Transfer asal tidak ditemukan.',
            ]);
        }

        if ((string) $origin->status !== StockTransferStatus::Received->value) {
            throw ValidationException::withMessages([
                'origin_transfer_id' => 'Retur hanya bisa dibuat dari transfer yang sudah diterima.',
            ]);
        }

        if (! $origin->fromWarehouse?->branch?->is_headquarters) {
            throw ValidationException::withMessages([
                'origin_transfer_id' => 'Retur transfer hanya bisa dikembalikan ke gudang Head Office.',
            ]);
        }

        if ((bool) $origin->toWarehouse?->branch?->is_headquarters) {
            throw ValidationException::withMessages([
                'origin_transfer_id' => 'Retur transfer hanya bisa dibuat untuk barang yang berada di cabang.',
            ]);
        }

        if ($origin->source_type === null || $origin->source_id === null) {
            throw ValidationException::withMessages([
                'origin_transfer_id' => 'Transfer asal tidak memiliki sumber pembelian yang bisa dilacak.',
            ]);
        }

        // Cabang mengembalikan ke gudang asal transfer outbound.
        $fromWarehouseId = (int) $origin->to_warehouse_id;
        $toWarehouseId = (int) $origin->from_warehouse_id;
        $originBranchId = (int) $origin->toWarehouse->branch_id;

        $lines = $this->resolveLines($origin, $items, $fromWarehouseId, $originBranchId);

        return DB::transaction(function () use ($origin, $lines, $fromWarehouseId, $toWarehouseId, $createdById, $createdByName) {
            $transfer = StockTransfer::create([
                'stock_request_id' => null,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'status' => StockTransferStatus::PendingApproval->value,
                'source_type' => $origin->source_type,
                'source_id' => $origin->source_id,
                'created_by' => $createdById,
            ]);

            foreach ($lines as $line) {
                $transfer->items()->create([
                    'product_variant_id' => $line['product_variant_id'],
                    'qty' => $line['qty'],
                ]);
            }

            $this->approvalEngine->evaluateAndMap([
                'transaction_type' => 'stock_transfer',
                'transaction_id' => $transfer->id,
                'document_number' => $transfer->number ?? 'TRF/'.$transfer->id,
                'created_by' => $createdById,
                'created_by_name' => $createdByName,
                'branch_id' => (int) $origin->toWarehouse->branch_id,
                'total' => array_sum(array_column($lines, 'qty')),
                'currency_code' => 'QTY',
            ]);

            return $transfer->load(['items', 'fromWarehouse', 'toWarehouse']);
        });
    }

    /**
     * Validasi item terhadap transfer asal dan stok yang masih bisa
     * dikembalikan di gudang branch.
     *
     * @param  list<array{stock_transfer_item_id: int, qty: int}>  $items
     * @return list<array{product_variant_id: int, qty: int}>
     */
    private function resolveLines(
        StockTransfer $origin,
        array $items,
        int $fromWarehouseId,
        int $originBranchId,
    ): array {
        $originItems = $origin->items->keyBy('id');
        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $originItemId = (int) ($item['stock_transfer_item_id'] ?? 0);
            $qty = (int) ($item['qty'] ?? 0);

            /** @var StockTransferItem|null $originItem */
            $originItem = $originItems->get($originItemId);

            if (! $originItem) {
                throw ValidationException::withMessages([
                    "items.{$index}.stock_transfer_item_id" => 'Barang tidak ada di transfer asal.',
                ]);
            }

            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.qty" => 'Qty harus lebih dari 0.',
                ]);
            }

            /*
             * Item memakai variant milik gudang asal (branch), karena di
             * situlah stok berada dan FIFO mengambil dari sana. Master
             * HO dipulihkan lagi saat receive di gudang tujuan.
             */
            $resolvedVariant = $this->returnVariants->execute(
                (int) $originItem->product_variant_id,
                $originBranchId,
            );

            if ($resolvedVariant === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.stock_transfer_item_id" => 'Varian cabang untuk barang ini tidak ditemukan.',
                ]);
            }

            $variantId = (int) $resolvedVariant['variant_id'];

            $available = $this->availableStock->forItemsFromTransfer(
                $fromWarehouseId,
                [$variantId],
                (int) $origin->id,
            )[$variantId] ?? 0;

            if ($qty > $available) {
                throw ValidationException::withMessages([
                    "items.{$index}.qty" => sprintf(
                        'Stok yang bisa dikembalikan dari transfer ini hanya %s.',
                        rtrim(rtrim(number_format($available, 4), '0'), '.'),
                    ),
                ]);
            }

            $lines[] = [
                'product_variant_id' => $variantId,
                'qty' => $qty,
            ];
        }

        return $lines;
    }
}
