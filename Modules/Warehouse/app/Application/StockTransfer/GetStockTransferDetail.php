<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    /**
     * Varian scope cabang: transfer yang from/to warehouse-nya di luar
     * cabang peminta dianggap tak ada (404), agar tak bisa di-probe.
     *
     * @param  list<int>  $branchIds
     */
    public function forScope(int $id, array $branchIds): StockTransfer
    {
        $transfer = $this->execute($id);

        $fromBranchId = (int) ($transfer->fromWarehouse?->branch_id ?? 0);
        $toBranchId = (int) ($transfer->toWarehouse?->branch_id ?? 0);

        if (! in_array($fromBranchId, $branchIds, true) && ! in_array($toBranchId, $branchIds, true)) {
            throw new ModelNotFoundException;
        }

        return $transfer;
    }
}
