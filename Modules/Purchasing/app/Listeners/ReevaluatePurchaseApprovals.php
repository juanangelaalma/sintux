<?php

namespace Modules\Purchasing\Listeners;

use Modules\Approval\Application\ApprovalEngine;
use Modules\Approval\Events\ApprovalRuleChanged;
use Modules\Purchasing\Models\PurchaseInvoice;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Models\PurchaseRequest;

class ReevaluatePurchaseApprovals
{
    public function __construct(
        private readonly ApprovalEngine $engine,
    ) {}

    public function handle(ApprovalRuleChanged $event): void
    {
        if (! $event->applyToExistingDraft) {
            return;
        }

        switch ($event->transactionType) {
            case 'purchase_request':
                $prs = PurchaseRequest::where('status', 'pending')->get();
                foreach ($prs as $pr) {
                    $mapping = $this->engine->evaluateAndMap([
                        'transaction_type' => 'purchase_request',
                        'transaction_id' => $pr->id,
                        'document_number' => $pr->number,
                        'created_by' => 0, // engine resolves the real creator from the existing mapping
                        'total' => (float) ($pr->total ?? 0),
                        'currency_code' => $pr->currency_code ?? 'IDR',
                        'branch_id' => $pr->branch_id,
                    ]);

                    $pr->update(['status' => $mapping ? 'pending' : 'approved']);
                }
                break;

            case 'purchase_order':
                $pos = PurchaseOrder::where('status', 'pending')->get();
                foreach ($pos as $po) {
                    $mapping = $this->engine->evaluateAndMap([
                        'transaction_type' => 'purchase_order',
                        'transaction_id' => $po->id,
                        'document_number' => $po->number,
                        'created_by' => 0, // engine resolves the real creator from the existing mapping
                        'total' => (float) ($po->total ?? 0),
                        'currency_code' => $po->currency_code ?? 'IDR',
                        'branch_id' => $po->branch_id,
                    ]);

                    $po->update(['status' => $mapping ? 'pending' : 'approved']);
                }
                break;

            case 'purchase_invoice':
                $invs = PurchaseInvoice::where('status', 'pending')->get();
                foreach ($invs as $inv) {
                    $mapping = $this->engine->evaluateAndMap([
                        'transaction_type' => 'purchase_invoice',
                        'transaction_id' => $inv->id,
                        'document_number' => $inv->number,
                        'created_by' => 0, // engine resolves the real creator from the existing mapping
                        'total' => (float) ($inv->total ?? 0),
                        'currency_code' => $inv->currency_code ?? 'IDR',
                        'branch_id' => $inv->branch_id,
                    ]);

                    $inv->update(['status' => $mapping ? 'pending' : 'approved']);
                }
                break;
        }
    }
}
