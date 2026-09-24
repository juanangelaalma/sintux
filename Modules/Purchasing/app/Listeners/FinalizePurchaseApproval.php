<?php

namespace Modules\Purchasing\Listeners;

use Modules\Approval\Events\TransactionApprovalFinalized;
use Modules\Purchasing\Application\PurchaseInvoice\IncrementInvoicedQuantities;
use Modules\Purchasing\Application\PurchaseInvoice\ValidateInvoiceQuantities;
use Modules\Purchasing\Application\PurchaseOrder\MarkPurchaseOrderClosed;
use Modules\Purchasing\Application\PurchaseReturn\FinalizePurchaseReturn;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Enums\PurchaseRequestStatus;
use Modules\Purchasing\Enums\PurchaseReturnStatus;
use Modules\Purchasing\Models\PurchaseInvoice;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Models\PurchaseRequest;
use Modules\Purchasing\Models\PurchaseReturn;

class FinalizePurchaseApproval
{
    public function handle(TransactionApprovalFinalized $event): void
    {
        $type = $event->transactionType;
        $id = $event->transactionId;
        $status = $event->status; // 'approved' or 'rejected'

        switch ($type) {
            case 'purchase_request':
                $pr = PurchaseRequest::find($id);
                if ($pr) {
                    $mapped = $status === 'rejected' ? PurchaseRequestStatus::Cancelled : PurchaseRequestStatus::from($status);
                    $pr->update(['status' => $mapped]);
                }
                break;

            case 'purchase_order':
                $po = PurchaseOrder::find($id);
                if ($po) {
                    $mapped = $status === 'rejected' ? PurchaseOrderStatus::Cancelled : PurchaseOrderStatus::from($status);
                    $po->update(['status' => $mapped]);
                }
                break;

            case 'purchase_invoice':
                $inv = PurchaseInvoice::with('items')->find($id);
                if ($inv) {
                    if ($status === 'approved') {
                        // 3-way match kumulatif dulu; gagal = approval rollback.
                        app(ValidateInvoiceQuantities::class)->executeForInvoice($inv);

                        $lines = $inv->items->map(fn ($item) => [
                            'goods_receipt_item_id' => $item->goods_receipt_item_id,
                            'purchase_order_item_id' => $item->purchase_order_item_id,
                            'qty' => (float) $item->qty,
                        ])->all();

                        app(IncrementInvoicedQuantities::class)->execute($lines);

                        if ($inv->purchase_order_id) {
                            app(MarkPurchaseOrderClosed::class)->execute((int) $inv->purchase_order_id);
                        }
                    }
                    $mapped = $status === 'rejected' ? PurchaseInvoiceStatus::Cancelled : PurchaseInvoiceStatus::from($status);
                    $inv->update(['status' => $mapped]);
                }
                break;

            case 'purchase_return':
                $ret = PurchaseReturn::with('items')->find($id);
                if ($ret) {
                    if ($status === 'approved') {
                        // Stok + jurnal + faktur + memo; gagal = approval rollback
                        // (pola yang sama seperti purchase_invoice di atas).
                        app(FinalizePurchaseReturn::class)->execute($ret->id);
                    } else {
                        $ret->update(['status' => PurchaseReturnStatus::Rejected->value]);
                    }
                }
                break;
        }
    }
}
