<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Warehouse\Enums\StockTransferStatus;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockTransfer;
use Modules\Warehouse\Models\Warehouse;

class CreateDirectTransfer
{
    public function __construct(
        private readonly ApprovalEngine $approvalEngine,
    ) {}

    /**
     * Buat transfer stok langsung tanpa stock request.
     *
     * Aturan status:
     * - Satu branch yang sama (mis. Jakarta regular -> Jakarta retail)
     *   -> draft (pindah stok milik sendiri, tanpa approval).
     * - Gudang asal HQ ke branch lain -> draft (langsung bisa ship).
     * - Antar branch dari non-HQ -> dievaluasi via ApprovalEngine
     *   (tipe stock_transfer, basis total qty):
     *   - ada mapping (qty di atas threshold & ada approver efektif)
     *     -> pending_approval, approval via Inbox Approval.
     *   - tidak ada mapping tapi sudah ada rule aktif
     *     -> qty di bawah threshold, langsung draft.
     *   - tidak ada mapping dan belum ada rule aktif sama sekali
     *     -> pending_approval fallback (approve oleh anggota HO,
     *     perilaku lama untuk kompatibilitas).
     *
     * @param  array{
     *     from_warehouse_id: int,
     *     to_warehouse_id: int,
     *     items: list<array{product_variant_id: int, qty: float|int}>
     * }  $data
     */
    public function execute(array $data, int $createdById, ?string $createdByName = null): StockTransfer
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

        $toWarehouse = Warehouse::find($toWarehouseId);

        if (! $toWarehouse) {
            throw ValidationException::withMessages([
                'to_warehouse_id' => 'Gudang tujuan tidak ditemukan.',
            ]);
        }

        $isHqOrigin = (bool) ($fromWarehouse->branch && $fromWarehouse->branch->is_headquarters);
        $isSameBranch = (int) $fromWarehouse->branch_id === (int) $toWarehouse->branch_id;
        $creatorName = $createdByName ?? auth()->user()?->name;

        /*
         * Pindah stok dalam satu branch atau dari HQ:
         * langsung draft, wajib lolos cek stok saat create.
         */
        if ($isSameBranch || $isHqOrigin) {
            $this->assertSufficientStock($fromWarehouseId, $data['items']);

            return DB::transaction(function () use ($data, $createdById, $fromWarehouseId, $toWarehouseId) {
                $transfer = StockTransfer::create([
                    'stock_request_id' => null,
                    'from_warehouse_id' => $fromWarehouseId,
                    'to_warehouse_id' => $toWarehouseId,
                    'status' => StockTransferStatus::Draft->value,
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

        /*
         * Antar branch dari non-HQ: buat dulu sebagai pending, lalu evaluasi
         * approval dalam transaksi yang sama (pola yang sama
         * dengan CreatePurchaseOrder).
         */
        return DB::transaction(function () use ($data, $createdById, $creatorName, $fromWarehouse, $fromWarehouseId, $toWarehouseId) {
            $transfer = StockTransfer::create([
                'stock_request_id' => null,
                'from_warehouse_id' => $fromWarehouseId,
                'to_warehouse_id' => $toWarehouseId,
                'status' => StockTransferStatus::PendingApproval->value,
                'created_by' => $createdById,
            ]);

            foreach ($data['items'] as $item) {
                $transfer->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'qty' => $item['qty'],
                ]);
            }

            $totalQty = collect($data['items'])->sum(fn ($item) => (float) $item['qty']);

            $mapping = $this->approvalEngine->evaluateAndMap([
                'transaction_type' => 'stock_transfer',
                'transaction_id' => $transfer->id,
                'document_number' => 'ST-'.$transfer->id,
                'created_by' => $createdById,
                'created_by_name' => $creatorName,
                'branch_id' => $fromWarehouse->branch_id,
                'total' => $totalQty,
                'currency_code' => 'QTY',
            ]);

            if ($mapping) {
                // Butuh approval sesuai rule. Stok dicek saat approve final / ship
                // karena stok bisa berubah selama menunggu persetujuan.
                return $transfer->load(['items', 'fromWarehouse', 'toWarehouse']);
            }

            if ($this->approvalEngine->hasActiveRules('stock_transfer')) {
                // Ada rule aktif tapi qty di bawah threshold -> langsung draft.
                $this->assertSufficientStock($fromWarehouseId, $data['items']);
                $transfer->update(['status' => StockTransferStatus::Draft->value]);
            }

            // Tanpa rule aktif: tetap pending_approval sebagai fallback
            // (approve oleh anggota HO via endpoint lama).

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
