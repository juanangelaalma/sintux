<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Modules\Purchasing\Models\GoodsReceipt;

class GetGoodsReceiptDetail
{
    public function execute(int $id): GoodsReceipt
    {
        return GoodsReceipt::with(['items'])
            ->findOrFail($id);
    }
}
