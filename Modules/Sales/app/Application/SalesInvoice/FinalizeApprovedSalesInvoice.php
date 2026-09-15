<?php

namespace Modules\Sales\Application\SalesInvoice;

use Modules\Sales\Enums\SalesInvoiceStatus;
use Modules\Sales\Events\SalesInvoiceApproved;
use Modules\Sales\Models\SalesInvoice;
use Modules\Warehouse\Application\StockReservation\ConsumeReservedStock;

/**
 * Finalisasi faktur menjadi Approved: konsumsi reservasi (FIFO),
 * set status, dispatch event untuk Accounting.
 *
 * Dipakai jalur auto-final (tanpa rule) dan finalize approval.
 * Berjalan dalam transaksi caller: gagal = rollback penuh.
 */
class FinalizeApprovedSalesInvoice
{
    public function __construct(
        private readonly ConsumeReservedStock $consumeReservedStock,
    ) {}

    public function execute(SalesInvoice $invoice): void
    {
        $lines = $invoice->items->map(fn ($item) => [
            'product_variant_id' => (int) $item->product_variant_id,
            'qty' => (int) $item->qty,
        ])->all();

        $this->consumeReservedStock->execute(
            $invoice->id,
            (int) $invoice->warehouse_id,
            $lines,
            SalesInvoice::class
        );

        $invoice->update(['status' => SalesInvoiceStatus::Approved]);

        SalesInvoiceApproved::dispatch($invoice->id);
    }
}
