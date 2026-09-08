<?php

namespace Modules\Purchasing\Listeners;

use Modules\Approval\Events\TransactionApprovalFinalized;
use Modules\Purchasing\Application\PurchaseInvoice\ValidateInvoiceQuantities;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Enums\PurchaseRequestStatus;
use Modules\Purchasing\Models\PurchaseInvoice;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Models\PurchaseRequest;

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
                $inv = PurchaseInvoice::find($id);
                if ($inv) {
                    if ($status === 'approved') {
                        app(ValidateInvoiceQuantities::class)->execute($inv);
                    }
                    $mapped = $status === 'rejected' ? PurchaseInvoiceStatus::Cancelled : PurchaseInvoiceStatus::from($status);
                    $inv->update(['status' => $mapped]);
                }
                break;
        }
    }
}
