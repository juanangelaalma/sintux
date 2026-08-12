<?php

namespace Modules\Warehouse\Application\StockRequest;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockRequest;
use Modules\Warehouse\Models\StockTransfer;

class ApproveStockRequest
{
    /**
     * Approve or partially approve / reject a stock request.
     *
     * @param  array{
     *     items: list<array{product_variant_id: int, qty_approved: float|int}>
     * }  $data
     */
    public function execute(int $stockRequestId, array $data, int $approvedById): StockRequest
    {
        $stockRequest = StockRequest::with(['items', 'requestingWarehouse', 'destinationWarehouse'])
            ->findOrFail($stockRequestId);

        if ($stockRequest->status !== 'pending') {
            throw ValidationException::withMessages([
                'stock_request' => 'Permintaan stok ini sudah diproses dan tidak dapat diubah.',
            ]);
        }

        $itemsInput = collect($data['items'])->keyBy('product_variant_id');

        $hqWarehouseId = $stockRequest->destination_warehouse_id;
        $variantIds = $stockRequest->items->pluck('product_variant_id')->all();

        $stockBalances = StockBalance::where('warehouse_id', $hqWarehouseId)
            ->whereIn('product_variant_id', $variantIds)
            ->pluck('qty_on_hand', 'product_variant_id');

        // Validation pass
        foreach ($stockRequest->items as $item) {
            $input = $itemsInput->get($item->product_variant_id);
            $qtyApproved = (float) ($input['qty_approved'] ?? 0);
            $qtyRequested = (float) $item->qty_requested;
            $availableStock = (float) ($stockBalances[$item->product_variant_id] ?? 0);

            if ($qtyApproved > $qtyRequested) {
                throw ValidationException::withMessages([
                    'items' => "Jumlah yang disetujui ({$qtyApproved}) tidak boleh melebihi jumlah yang diminta ({$qtyRequested}).",
                ]);
            }

            if ($qtyApproved > $availableStock) {
                throw ValidationException::withMessages([
                    'items' => "Jumlah yang disetujui ({$qtyApproved}) melebihi stok yang tersedia di Gudang HQ ({$availableStock}).",
                ]);
            }
        }

        return DB::transaction(function () use ($stockRequest, $itemsInput, $approvedById, $hqWarehouseId) {
            $allFullyApproved = true;
            $anyApproved = false;

            foreach ($stockRequest->items as $item) {
                $input = $itemsInput->get($item->product_variant_id);
                $qtyApproved = (float) ($input['qty_approved'] ?? 0);
                $qtyRequested = (float) $item->qty_requested;

                $item->update(['qty_approved' => $qtyApproved]);

                if ($qtyApproved > 0) {
                    $anyApproved = true;
                }

                if ($qtyApproved < $qtyRequested) {
                    $allFullyApproved = false;
                }
            }

            if ($allFullyApproved) {
                $finalStatus = 'approved';
            } elseif ($anyApproved) {
                $finalStatus = 'partially_approved';
            } else {
                $finalStatus = 'rejected';
            }

            $stockRequest->update(['status' => $finalStatus]);

            if ($finalStatus !== 'rejected') {
                $stockTransfer = StockTransfer::create([
                    'stock_request_id' => $stockRequest->id,
                    'from_warehouse_id' => $hqWarehouseId,
                    'to_warehouse_id' => $stockRequest->requesting_warehouse_id,
                    'status' => 'draft',
                ]);

                foreach ($stockRequest->items as $item) {
                    if ((float) $item->qty_approved > 0) {
                        $stockTransfer->items()->create([
                            'product_variant_id' => $item->product_variant_id,
                            'qty' => $item->qty_approved,
                        ]);
                    }
                }
            }

            return $stockRequest->load(['items', 'transfer.items']);
        });
    }
}
