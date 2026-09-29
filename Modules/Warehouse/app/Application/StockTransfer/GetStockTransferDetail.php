<?php

namespace Modules\Warehouse\Application\StockTransfer;

use Illuminate\Database\Eloquent\Builder;
use Modules\Warehouse\Models\StockTransfer;

class GetStockTransferDetail
{
    /**
     * Varian scope cabang: transfer yang from/to warehouse-nya di luar
     * cabang peminta dianggap tak ada (404), agar tak bisa di-probe.
     *
     * Scope diterapkan di query, bukan setelah load, supaya transfer di luar
     * scope tidak pernah ter eager-load dengan seluruh relasinya.
     *
     * @param  list<int>  $branchIds
     */
    public function forScope(int $id, array $branchIds): StockTransfer
    {
        $transfer = $this->withRelations()
            ->whereKey($id)
            ->where(function ($query) use ($branchIds) {
                $query->whereHas('fromWarehouse', fn ($w) => $w->whereIn('branch_id', $branchIds))
                    ->orWhereHas('toWarehouse', fn ($w) => $w->whereIn('branch_id', $branchIds));
            })
            ->firstOrFail();

        return $this->normalizeUserRelations($transfer);
    }

    public function execute(int $id): StockTransfer
    {
        $transfer = $this->withRelations()->findOrFail($id);

        return $this->normalizeUserRelations($transfer);
    }

    /**
     * @return Builder<StockTransfer>
     */
    private function withRelations(): Builder
    {
        return StockTransfer::with([
            'fromWarehouse.branch',
            'toWarehouse.branch',
            'shippedBy',
            'receivedBy',
            'items.productVariant.product.uom',
            'items.layers.stockLayer.warehouse',
            'items.discrepancies',
            'originTransfer:id,number,status,from_warehouse_id,to_warehouse_id',
            // RTRF turunan: gudang asalnya dipakai untuk membaca arah
            // gerak barang di tab Retur, dan items dipakai untuk total
            // qty. created_at/received_at dipakai sebagai tanggal dibuat
            // dan tanggal barang benar-benar sampai di gudang HO.
            'returnTransfers:id,origin_transfer_id,number,status,from_warehouse_id,to_warehouse_id,created_at,received_at',
            'returnTransfers.fromWarehouse.branch',
            'returnTransfers.toWarehouse.branch',
            'returnTransfers.items:id,stock_transfer_id,qty',
        ]);
    }

    /**
     * Eloquent menaruh relasi ter-load di key snake_case sehingga
     * menimpa atribut FK integer (shipped_by/received_by jadi objek).
     * Pindahkan ke key kontrak frontend dan kembalikan FK-nya.
     */
    private function normalizeUserRelations(StockTransfer $transfer): StockTransfer
    {
        $transfer->setRelation('shipped_by_user', $transfer->getRelation('shippedBy'));
        $transfer->unsetRelation('shippedBy');
        $transfer->setRelation('received_by_user', $transfer->getRelation('receivedBy'));
        $transfer->unsetRelation('receivedBy');

        return $transfer;
    }
}
