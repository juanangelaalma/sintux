<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Models\GoodsReceipt;

/**
 * Prefill form faktur dari GRN yang sudah diposting.
 *
 * Tiap baris terisi sisa belum tertagih (received − invoiced) dengan
 * harga dikunci dari PO. Baris yang sudah habis tertagih dilewati;
 * bila tak ada sisa, caller menampilkan peringatan.
 *
 * @return array{
 *     id: int,
 *     number: string,
 *     branch_id: int,
 *     supplier_id: int,
 *     purchase_order_id: int,
 *     is_tax_inclusive: bool,
 *     due_date: string|null,
 *     items: list<array{
 *         goods_receipt_item_id: int,
 *         purchase_order_item_id: int|null,
 *         product_variant_id: int,
 *         product_name: string,
 *         sku: string,
 *         qty_receivable: float,
 *         unit_price: float,
 *         tax_id: int|null
 *     }>
 * }
 */
class GetInvoicePrefillFromGrn
{
    public function execute(int $goodsReceiptId): array
    {
        $grn = GoodsReceipt::with(['items.purchaseOrderItem'])->find($goodsReceiptId);

        if (! $grn) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => 'Penerimaan barang tidak ditemukan.',
            ]);
        }

        if ($grn->status !== GoodsReceiptStatus::Posted) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => 'Faktur hanya dapat dibuat dari penerimaan barang yang sudah diposting.',
            ]);
        }

        $po = DB::table('purchase_orders')->where('id', $grn->purchase_order_id)->first();

        $items = [];

        foreach ($grn->items as $item) {
            $receivable = (float) $item->qty_received - (float) ($item->qty_invoiced ?? 0);

            if ($receivable <= 0) {
                continue;
            }

            $items[] = [
                'goods_receipt_item_id' => (int) $item->id,
                'purchase_order_item_id' => $item->purchase_order_item_id ? (int) $item->purchase_order_item_id : null,
                'product_variant_id' => (int) $item->product_variant_id,
                'product_name' => (string) $item->product_name,
                'sku' => (string) $item->sku,
                'qty_receivable' => $receivable,
                'unit_price' => $item->purchaseOrderItem ? (float) $item->purchaseOrderItem->unit_price : 0.0,
                'tax_id' => $item->purchaseOrderItem?->tax_id ? (int) $item->purchaseOrderItem->tax_id : null,
            ];
        }

        return [
            'id' => (int) $grn->id,
            'number' => (string) $grn->number,
            'branch_id' => (int) $grn->branch_id,
            'supplier_id' => (int) $grn->supplier_id,
            'purchase_order_id' => (int) $grn->purchase_order_id,
            'is_tax_inclusive' => (bool) ($po->is_tax_inclusive ?? false),
            'due_date' => $po->due_date ? (string) $po->due_date : null,
            'items' => $items,
        ];
    }
}
