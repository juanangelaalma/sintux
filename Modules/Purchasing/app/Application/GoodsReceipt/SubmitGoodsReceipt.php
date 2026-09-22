<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Purchasing\Models\GoodsReceiptItem;

class SubmitGoodsReceipt
{
    public function execute(int $goodsReceiptId, int $branchId, int $userId): GoodsReceipt
    {
        return DB::transaction(function () use ($goodsReceiptId, $branchId, $userId) {
            $grn = GoodsReceipt::with(['items'])
                ->lockForUpdate()
                ->findOrFail($goodsReceiptId);

            if ((int) $grn->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'grn' => 'Penerimaan ini bukan milik cabang Anda.',
                ]);
            }

            if (! $grn->status->canSubmit()) {
                throw ValidationException::withMessages([
                    'grn' => "Penerimaan sudah {$grn->status->label()}.",
                ]);
            }

            if ($grn->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'grn' => 'Penerimaan tidak memiliki barang.',
                ]);
            }

            $unverified = $grn->items
                ->reject(fn (GoodsReceiptItem $item) => $item->isVerified())
                ->map(fn (GoodsReceiptItem $item) => (string) $item->supplier_barcode)
                ->unique()
                ->count();

            if ($unverified > 0) {
                throw ValidationException::withMessages([
                    'grn' => "Masih ada {$unverified} bundle belum terverifikasi scan.",
                ]);
            }

            $unmapped = $grn->items
                ->reject(fn (GoodsReceiptItem $item) => $item->isMapped())
                ->count();

            if ($unmapped > 0) {
                throw ValidationException::withMessages([
                    'grn' => "Masih ada {$unmapped} barang belum di-mapping.",
                ]);
            }

            $unconfirmed = $grn->items
                ->reject(fn (GoodsReceiptItem $item) => (bool) $item->qty_confirmed)
                ->map(fn (GoodsReceiptItem $item) => (string) $item->supplier_barcode)
                ->unique()
                ->count();

            if ($unconfirmed > 0) {
                throw ValidationException::withMessages([
                    'grn' => "Masih ada {$unconfirmed} bundle belum konfirmasi hitung fisik. Pastikan qty sesuai barang fisik yang datang.",
                ]);
            }

            $grn->update([
                'status' => GoodsReceiptStatus::Submitted,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            return $grn->fresh(['items']);
        });
    }
}
