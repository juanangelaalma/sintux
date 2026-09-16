<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Modules\Purchasing\Models\GoodsReceipt;

class GetGoodsReceiptDetail
{
    /**
     * Detail GRN berorientasi halaman: item diperkaya cabang tujuan
     * alokasi yang dibaca dari PO item-nya (pola sama seperti
     * GetPurchaseOrderOptions me-resolve cabang via tabel branches).
     *
     * @return array<string, mixed>
     */
    public function execute(int $id): array
    {
        $receipt = GoodsReceipt::with(['items.purchaseOrderItem', 'purchaseOrder'])
            ->findOrFail($id);

        $branches = DB::table('branches')
            ->whereIn('id', $receipt->items
                ->map(fn ($item) => $item->purchaseOrderItem?->destination_branch_id)
                ->filter()
                ->unique()
                ->values()
                ->all())
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        return [
            'id' => $receipt->id,
            'number' => $receipt->number,
            'purchase_order_id' => $receipt->purchase_order_id,
            'warehouse_id' => $receipt->warehouse_id,
            'status' => $receipt->status->value,
            'receipt_date' => $receipt->receipt_date->toDateString(),
            'note' => $receipt->note,
            'purchase_order' => $receipt->purchaseOrder
                ? ['id' => $receipt->purchaseOrder->id, 'number' => $receipt->purchaseOrder->number]
                : null,
            'items' => $receipt->items->map(function ($item) use ($branches): array {
                $branch = $branches->get($item->purchaseOrderItem?->destination_branch_id);

                return [
                    'id' => $item->id,
                    'product_name' => $item->product_name,
                    'sku' => $item->sku,
                    'uom_name' => $item->uom_name,
                    'qty_received' => (float) $item->qty_received,
                    'qty_invoiced' => (float) ($item->qty_invoiced ?? 0),
                    'destination_branch' => $branch
                        ? ['id' => $branch->id, 'code' => $branch->code, 'name' => $branch->name]
                        : null,
                ];
            })->values()->all(),
        ];
    }
}
