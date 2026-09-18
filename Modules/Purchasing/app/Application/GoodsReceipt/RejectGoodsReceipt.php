<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Models\GoodsReceipt;

class RejectGoodsReceipt
{
    public function execute(int $goodsReceiptId, int $userId, string $reason): GoodsReceipt
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Alasan penolakan wajib diisi agar cabang tahu yang harus direvisi.',
            ]);
        }

        return DB::transaction(function () use ($goodsReceiptId, $userId, $reason) {
            $grn = GoodsReceipt::lockForUpdate()->findOrFail($goodsReceiptId);

            if (! $grn->status->canDecide()) {
                throw ValidationException::withMessages([
                    'grn' => "Penerimaan sudah {$grn->status->label()}.",
                ]);
            }

            $grn->update([
                'status' => GoodsReceiptStatus::Rejected,
                'rejection_reason' => mb_substr($reason, 0, 1000),
                'decided_by' => $userId,
                'decided_at' => now(),
            ]);

            return $grn->fresh(['items']);
        });
    }
}
