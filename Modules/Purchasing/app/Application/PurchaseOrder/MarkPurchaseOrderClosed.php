<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Illuminate\Support\Facades\DB;
use Modules\Purchasing\Enums\PurchaseOrderStatus;

/**
 * Tandai PO closed bila seluruh item sudah tertagih penuh.
 *
 * Dipanggil setiap faktur menjadi Approved. Tidak menyentuh PO
 * berstatus lain selain received/partially_received.
 */
class MarkPurchaseOrderClosed
{
    public function execute(int $purchaseOrderId): void
    {
        $po = DB::table('purchase_orders')->where('id', $purchaseOrderId)->first();

        if (! $po) {
            return;
        }

        if (! in_array($po->status, [
            PurchaseOrderStatus::Received->value,
            PurchaseOrderStatus::PartiallyReceived->value,
        ], true)) {
            return;
        }

        $openItems = DB::table('purchase_order_items')
            ->where('purchase_order_id', $purchaseOrderId)
            ->whereRaw('qty_invoiced < qty_ordered')
            ->count();

        if ($openItems === 0) {
            DB::table('purchase_orders')
                ->where('id', $purchaseOrderId)
                ->update(['status' => PurchaseOrderStatus::Closed->value, 'updated_at' => now()]);
        }
    }
}
