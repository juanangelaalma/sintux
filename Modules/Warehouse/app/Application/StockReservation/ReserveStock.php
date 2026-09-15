<?php

namespace Modules\Warehouse\Application\StockReservation;

use Illuminate\Validation\ValidationException;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockReservation;

/**
 * Lock quantities for a sales invoice so concurrent transactions
 * cannot sell the same stock twice.
 *
 * Must run inside the caller's DB transaction (bersama create
 * invoice + approval mapping) agar alokasi bersifat atomik.
 */
class ReserveStock
{
    public function __construct(
        private readonly GetAvailableStock $availableStock,
    ) {}

    /**
     * @param  list<array{product_variant_id: int, qty: int}>  $lines
     */
    public function execute(int $salesInvoiceId, int $warehouseId, array $lines): void
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

        // Kunci balance agar dua reservasi konkuren tidak over-allocate.
        StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('product_variant_id', array_keys($totals))
            ->lockForUpdate()
            ->get();

        $available = $this->availableStock->forItems($warehouseId, array_keys($totals));

        foreach ($totals as $variantId => $qty) {
            if (($available[$variantId] ?? 0) < $qty) {
                throw ValidationException::withMessages([
                    "items.{$variantId}.qty" => sprintf(
                        'Stok product variant %d tidak mencukupi di gudang %d. Tersedia: %d, diminta: %d.',
                        $variantId,
                        $warehouseId,
                        $available[$variantId] ?? 0,
                        $qty
                    ),
                ]);
            }
        }

        foreach ($totals as $variantId => $qty) {
            StockReservation::create([
                'sales_invoice_id' => $salesInvoiceId,
                'warehouse_id' => $warehouseId,
                'product_variant_id' => $variantId,
                'qty' => $qty,
            ]);
        }
    }
}
