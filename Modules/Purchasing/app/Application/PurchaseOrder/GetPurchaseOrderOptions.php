<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Modules\Purchasing\Models\PurchaseOrder;

class GetPurchaseOrderOptions
{
    /**
     * @param  list<int>  $branchIds
     * @return list<array<string, mixed>>
     */
    public function execute(array $branchIds): array
    {
        return PurchaseOrder::query()
            ->with(['items'])
            ->whereIn('branch_id', $branchIds)
            ->orderByDesc('id')
            ->get()
            ->map(function (PurchaseOrder $po) {
                return [
                    'id' => $po->id,
                    'number' => $po->number,
                    'branch_id' => $po->branch_id,
                    'supplier_id' => $po->supplier_id,
                    'order_date' => $po->order_date,
                    'expected_date' => $po->expected_date,
                    'note' => $po->note,
                    'subtotal' => (float) $po->subtotal,
                    'total' => (float) $po->total,
                    'items' => $po->items->map(function ($item) {
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
                        ];
                    })->values()->all(),
                ];
            })
            ->all();
    }
}
