<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Purchasing\Models\GoodsReceiptItem;

class VerifyBundleBarcode
{
    /**
     * Verifikasi satu bundle (seluruh baris warna dalam barcode yang sama).
     *
     * @return list<GoodsReceiptItem>
     */
    public function execute(int $goodsReceiptId, string $scannedBarcode, int $branchId): array
    {
        $scannedBarcode = trim($scannedBarcode);

        if ($scannedBarcode === '') {
            throw ValidationException::withMessages([
                'barcode' => 'Barcode tidak boleh kosong.',
            ]);
        }

        return DB::transaction(function () use ($goodsReceiptId, $scannedBarcode, $branchId) {
            $grn = GoodsReceipt::findOrFail($goodsReceiptId);

            if ((int) $grn->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'barcode' => 'Penerimaan ini bukan milik cabang Anda.',
                ]);
            }

            if (! $grn->status->canSubmit()) {
                throw ValidationException::withMessages([
                    'barcode' => "Penerimaan sudah {$grn->status->label()}, tidak bisa diverifikasi lagi.",
                ]);
            }

            $items = GoodsReceiptItem::where('goods_receipt_id', $grn->id)
                ->where('supplier_barcode', $scannedBarcode)
                ->get();

            if ($items->isEmpty()) {
                throw ValidationException::withMessages([
                    'barcode' => "Barcode {$scannedBarcode} tidak ada di DO ini.",
                ]);
            }

            foreach ($items as $item) {
                if (! $item->isVerified()) {
                    $item->update([
                        'verification_status' => GoodsReceiptItem::VERIFY_VERIFIED,
                        'scanned_barcode' => $scannedBarcode,
                        'scanned_at' => now(),
                    ]);
                }
            }

            return $items->map(fn (GoodsReceiptItem $item) => $item->fresh())->all();
        });
    }
}
