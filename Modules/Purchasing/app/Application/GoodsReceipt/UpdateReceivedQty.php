<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Purchasing\Models\GoodsReceiptItem;

class UpdateReceivedQty
{
    public function updateItem(int $itemId, float $qty, int $branchId): GoodsReceiptItem
    {
        return DB::transaction(function () use ($itemId, $qty, $branchId) {
            $item = GoodsReceiptItem::with(['receipt'])->findOrFail($itemId);
            $this->guardDraft($item->receipt, $branchId);
            $this->guardQty($qty, (float) $item->qty_do);

            $item->update(['qty_received' => $qty]);

            // Ubah qty → konfirmasi fisik bundle hangus (semua warna se-bundle).
            GoodsReceiptItem::where('goods_receipt_id', $item->goods_receipt_id)
                ->where('supplier_barcode', $item->supplier_barcode)
                ->update(['qty_confirmed' => false]);

            return $item->fresh();
        });
    }

    private function guardDraft(GoodsReceipt $grn, int $branchId): void
    {
        if ((int) $grn->branch_id !== $branchId) {
            throw ValidationException::withMessages([
                'qty_received' => 'Penerimaan ini bukan milik cabang Anda.',
            ]);
        }

        if (! $grn->status->canSubmit()) {
            throw ValidationException::withMessages([
                'qty_received' => "Penerimaan sudah {$grn->status->label()}, qty tidak bisa diubah.",
            ]);
        }
    }

    private function guardQty(float $qty, float $qtyDo): void
    {
        if ($qty < 0 || $qty > $qtyDo + 0.0001) {
            throw ValidationException::withMessages([
                'qty_received' => "Qty diterima harus antara 0 dan {$qtyDo}.",
            ]);
        }
    }
}
