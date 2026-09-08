<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Warehouse\Application\StockLayer\ReceivePurchaseStock;

class PostGoodsReceipt
{
    public function __construct(
        private readonly ReceivePurchaseStock $receivePurchaseStock,
    ) {}

    public function execute(int $goodsReceiptId): GoodsReceipt
    {
        $grn = GoodsReceipt::with(['items'])->findOrFail($goodsReceiptId);

        if (! $grn->status->canPost()) {
            throw ValidationException::withMessages([
                'grn' => 'Penerimaan barang hanya dapat diposting saat berstatus draft.',
            ]);
        }

        return DB::transaction(function () use ($grn) {
            // 1. Post stock to Warehouse for each item via public API
            foreach ($grn->items as $item) {
                // Find unit price from PO item if present
                $unitCost = 0.0;
                if ($item->purchase_order_item_id) {
                    $poItem = DB::table('purchase_order_items')->where('id', $item->purchase_order_item_id)->first();
                    if ($poItem) {
                        $unitCost = (float) $poItem->unit_price;
                    }
                }

                $this->receivePurchaseStock->execute([
                    'warehouse_id' => (int) $grn->warehouse_id,
                    'product_variant_id' => (int) $item->product_variant_id,
                    'qty' => (float) $item->qty_received,
                    'unit_cost' => $unitCost,
                    'received_at' => $grn->receipt_date,
                    'source_type' => 'purchase_order',
                    'source_id' => (int) $grn->purchase_order_id,
                    'reference_type' => 'Modules\Purchasing\Models\GoodsReceipt',
                ]);

                // 2. Increment qty_received on purchase_order_item
                if ($item->purchase_order_item_id) {
                    DB::table('purchase_order_items')
                        ->where('id', $item->purchase_order_item_id)
                        ->increment('qty_received', (float) $item->qty_received);
                }
            }

            // 3. Update PO status: received if all fulfilled, partially_received if any received but not all
            $poId = $grn->purchase_order_id;
            $unfulfilled = DB::table('purchase_order_items')
                ->where('purchase_order_id', $poId)
                ->whereRaw('qty_received < qty_ordered')
                ->count();

            $anyReceived = DB::table('purchase_order_items')
                ->where('purchase_order_id', $poId)
                ->where('qty_received', '>', 0)
                ->exists();

            if ($unfulfilled === 0) {
                DB::table('purchase_orders')
                    ->where('id', $poId)
                    ->update(['status' => PurchaseOrderStatus::Received->value, 'updated_at' => now()]);
            } elseif ($anyReceived) {
                DB::table('purchase_orders')
                    ->where('id', $poId)
                    ->whereIn('status', [PurchaseOrderStatus::Sent->value, PurchaseOrderStatus::PartiallyReceived->value])
                    ->update(['status' => PurchaseOrderStatus::PartiallyReceived->value, 'updated_at' => now()]);
            }

            // 4. Update GRN status to posted
            $grn->update(['status' => GoodsReceiptStatus::Posted]);

            return $grn->fresh(['items']);
        });
    }
}
