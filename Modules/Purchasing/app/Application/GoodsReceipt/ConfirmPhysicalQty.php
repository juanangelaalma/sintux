<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\GoodsReceiptItem;

class ConfirmPhysicalQty
{
    /**
     * Konfirmasi hitung fisik satu bundle (seluruh baris warna se-barcode).
     */
    public function execute(int $itemId, int $branchId, bool $confirmed): GoodsReceiptItem
    {
        return DB::transaction(function () use ($itemId, $branchId, $confirmed) {
            $item = GoodsReceiptItem::with(['receipt'])->findOrFail($itemId);

            if ((int) $item->receipt->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'qty_confirmed' => 'Penerimaan ini bukan milik cabang Anda.',
                ]);
            }

            if (! $item->receipt->status->canSubmit()) {
                throw ValidationException::withMessages([
                    'qty_confirmed' => "Penerimaan sudah {$item->receipt->status->label()}.",
                ]);
            }

            if ($confirmed) {
                $unverified = GoodsReceiptItem::where('goods_receipt_id', $item->goods_receipt_id)
                    ->where('supplier_barcode', $item->supplier_barcode)
                    ->where('verification_status', '!=', GoodsReceiptItem::VERIFY_VERIFIED)
                    ->count();

                if ($unverified > 0) {
                    throw ValidationException::withMessages([
                        'qty_confirmed' => 'Scan barcode dulu sebelum konfirmasi hitung fisik.',
                    ]);
                }
            }

            GoodsReceiptItem::where('goods_receipt_id', $item->goods_receipt_id)
                ->where('supplier_barcode', $item->supplier_barcode)
                ->update(['qty_confirmed' => $confirmed]);

            return $item->fresh();
        });
    }
}
