<?php

namespace Modules\Warehouse\Application\StockReservation;

use Modules\Warehouse\Models\StockReservation;

/**
 * Lepaskan reservasi tanpa mengurangi stok fisik.
 * Dipakai saat faktur penjualan dibatalkan (reject approval).
 */
class ReleaseStock
{
    /**
     * @return int Jumlah baris reservasi yang dihapus.
     */
    public function execute(int $salesInvoiceId): int
    {
        return StockReservation::query()
            ->where('sales_invoice_id', $salesInvoiceId)
            ->delete();
    }
}
