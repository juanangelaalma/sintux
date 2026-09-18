<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Models\GoodsReceipt;

class ReviseGoodsReceipt
{
    /**
     * Kembalikan GRN yang ditolak HO menjadi Draft agar cabang bisa
     * koreksi (qty/harga/mapping) lalu submit ulang. Alasan penolakan dan
     * penolak dipertahankan sebagai audit.
     */
    public function execute(int $goodsReceiptId, int $branchId): GoodsReceipt
    {
        return DB::transaction(function () use ($goodsReceiptId, $branchId) {
            $grn = GoodsReceipt::lockForUpdate()->findOrFail($goodsReceiptId);

            if ((int) $grn->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'grn' => 'Penerimaan ini bukan milik cabang Anda.',
                ]);
            }

            if (! $grn->status->canRevise()) {
                throw ValidationException::withMessages([
                    'grn' => "Penerimaan {$grn->status->label()} tidak dapat direvisi.",
                ]);
            }

            $grn->update(['status' => GoodsReceiptStatus::Draft]);

            return $grn->fresh(['items']);
        });
    }
}
