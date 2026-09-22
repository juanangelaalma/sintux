<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Modules\Warehouse\Models\StockTransfer;

class GetStockTransferDetail
{
    public function execute(int $id): StockTransfer
    {
        $transfer = StockTransfer::with([
            'fromWarehouse.branch',
            'toWarehouse.branch',
            'shippedBy',
            'receivedBy',
            'items.productVariant.product.uom',
            'items.layers.stockLayer.warehouse',
            'items.discrepancies',
        ])->findOrFail($id);

        // Eloquent menaruh relasi ter-load di key snake_case sehingga
        // menimpa atribut FK integer (shipped_by/received_by jadi objek).
        // Pindahkan ke key kontrak frontend dan kembalikan FK-nya.
        $transfer->setRelation('shipped_by_user', $transfer->getRelation('shippedBy'));
        $transfer->unsetRelation('shippedBy');
        $transfer->setRelation('received_by_user', $transfer->getRelation('receivedBy'));
        $transfer->unsetRelation('receivedBy');

        return $transfer;
    }
}
