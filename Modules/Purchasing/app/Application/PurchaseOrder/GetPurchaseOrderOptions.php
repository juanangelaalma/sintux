<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Illuminate\Support\Facades\DB;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Models\PurchaseOrder;

class GetPurchaseOrderOptions
{
    /**
     * POs eligible for goods receipt: approved, sent, or partially received.
     *
     * @param  list<int>  $accessibleBranchIds
     * @return list<array<string, mixed>>
     */
    public function execute(array $accessibleBranchIds): array
    {
        $purchaseOrders = PurchaseOrder::query()
            ->with(['items'])
            ->whereIn('branch_id', $accessibleBranchIds)
            ->whereIn('status', [
                PurchaseOrderStatus::Approved->value,
                PurchaseOrderStatus::Sent->value,
                PurchaseOrderStatus::PartiallyReceived->value,
            ])
            ->orderByDesc('id')
            ->get();

        $destinationBranchIds = $purchaseOrders
            ->flatMap(fn (PurchaseOrder $po) => $po->items->pluck('destination_branch_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $branchMap = DB::table('branches')
            ->whereIn('id', $destinationBranchIds)
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        return $purchaseOrders
            ->map(function (PurchaseOrder $po) use ($branchMap) {
                return [
                    'id' => $po->id,
                    'number' => $po->number,
                    'branch_id' => $po->branch_id,
                    'supplier_id' => $po->supplier_id,
                    'status' => $po->status->value,
                    'status_label' => $po->status->label(),
                    'order_date' => $po->order_date,
                    'expected_date' => $po->expected_date,
                    'note' => $po->note,
                    'subtotal' => (float) $po->subtotal,
                    'total' => (float) $po->total,
                    'items' => $po->items->map(function ($item) use ($branchMap) {
                        $destinationBranch = $branchMap->get($item->destination_branch_id);

                        return [
                            'id' => $item->id,
                            'product_variant_id' => $item->product_variant_id,
                            'product_name' => $item->product_name,
                            'sku' => $item->sku,
                            'qty' => (float) $item['qty_ordered'],
                            'qty_ordered' => (float) $item['qty_ordered'],
                            'qty_received' => (float) ($item['qty_received'] ?? 0),
                            'unit_price' => (float) $item['unit_price'],
                            'line_total' => (float) $item['line_total'],
                            'destination_branch_id' => $item->destination_branch_id,
                            'destination_branch_code' => $destinationBranch->code ?? null,
                            'destination_branch_name' => $destinationBranch->name ?? null,
                        ];
                    })->values()->all(),
                ];
            })
            ->all();
    }
}
