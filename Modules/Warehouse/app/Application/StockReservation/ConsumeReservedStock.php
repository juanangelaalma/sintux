<?php

namespace Modules\Warehouse\Application\StockReservation;

use Illuminate\Validation\ValidationException;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockMovement;
use Modules\Warehouse\Models\StockReservation;
use Modules\Warehouse\Services\FifoCostingService;

/**
 * Konsumsi stok yang sudah direservasi saat faktur penjualan
 * disetujui: FIFO consume + kurangi balance + movement sales_out
 * + hapus baris reservasi, dalam satu transaksi caller.
 *
 * Gagal (reservasi kurang / FIFO kurang) = exception agar finalize
 * approval ikut rollback seperti pola purchase invoice.
 */
class ConsumeReservedStock
{
    public function __construct(
        private readonly FifoCostingService $fifoCostingService,
    ) {}

    /**
     * @param  list<array{product_variant_id: int, qty: int}>  $lines
     */
    public function execute(int $salesInvoiceId, int $warehouseId, array $lines, string $referenceType): void
    {
        $totals = [];

        foreach ($lines as $line) {
            $variantId = (int) $line['product_variant_id'];
            $qty = (int) $line['qty'];

            if ($qty <= 0) {
                continue;
            }

            $totals[$variantId] = ($totals[$variantId] ?? 0) + $qty;
        }

        if ($totals === []) {
            return;
        }

        foreach ($totals as $variantId => $qty) {
            $reserved = (int) StockReservation::query()
                ->where('sales_invoice_id', $salesInvoiceId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->get()
                ->sum('qty');

            if ($reserved < $qty) {
                throw ValidationException::withMessages([
                    "items.{$variantId}.qty" => sprintf(
                        'Reservasi stok product variant %d tidak mencukupi (tereservasi: %d, diminta: %d).',
                        $variantId,
                        $reserved,
                        $qty
                    ),
                ]);
            }

            $breakdown = $this->fifoCostingService->consume($variantId, $warehouseId, $qty);

            $updatedRows = StockBalance::query()
                ->where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)
                ->where('qty_on_hand', '>=', $qty)
                ->decrement('qty_on_hand', $qty);

            if ($updatedRows === 0) {
                throw ValidationException::withMessages([
                    "items.{$variantId}.qty" => sprintf(
                        'Stok product variant %d tidak mencukupi di gudang %d.',
                        $variantId,
                        $warehouseId
                    ),
                ]);
            }

            foreach ($breakdown as $layer) {
                StockMovement::create([
                    'warehouse_id' => $warehouseId,
                    'product_variant_id' => $variantId,
                    'movement_type' => 'sales_out',
                    'qty' => -abs((float) $layer['qty_taken']),
                    'unit_cost' => $layer['unit_cost'],
                    'stock_layer_id' => $layer['stock_layer_id'],
                    'reference_type' => $referenceType,
                    'reference_id' => $salesInvoiceId,
                ]);
            }

            StockReservation::query()
                ->where('sales_invoice_id', $salesInvoiceId)
                ->where('warehouse_id', $warehouseId)
                ->where('product_variant_id', $variantId)
                ->delete();
        }
    }
}
