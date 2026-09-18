<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Models\GoodsReceipt;

/**
 * Prefill form faktur HO dari GRN yang sudah diposting (strict 1:1).
 *
 * Qty = received persis, harga = DO supplier (snapshot GRN), pajak ikut PO.
 * Menolak GRN yang sudah berfaktur atau belum diposting.
 *
 * @return array{
 *     id: int,
 *     number: string,
 *     branch_id: int,
 *     supplier_id: int,
 *     purchase_order_id: int,
 *     supplier_invoice_no: string|null,
 *     is_tax_inclusive: bool,
 *     due_date: string|null,
 *     items: list<array{
 *         goods_receipt_item_id: int,
 *         purchase_order_item_id: int|null,
 *         product_variant_id: int,
 *         product_name: string,
 *         sku: string,
 *         color_raw: string|null,
 *         color: string|null,
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

        if ($grn->status !== GoodsReceiptStatus::Approved) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => 'Faktur hanya dapat dibuat dari penerimaan barang yang sudah disetujui HO.',
            ]);
        }

        $alreadyInvoiced = DB::table('purchase_invoices')->where('goods_receipt_id', (int) $grn->id)->exists();
        if ($alreadyInvoiced) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => 'Penerimaan barang ini sudah memiliki faktur.',
            ]);
        }

        $po = DB::table('purchase_orders')->where('id', $grn->purchase_order_id)->first();

        $items = [];

        foreach ($grn->items as $item) {
            // Strict: seluruh sisa = received (qty_invoiced masih 0 karena unique).
            $receivable = (float) $item->qty_received - (float) ($item->qty_invoiced ?? 0);

            if ($receivable <= 0) {
                continue;
            }

            // Harga DO (snapshot GRN). Satu-satunya sumber kebenaran.
            $doPrice = (float) ($item->unit_price_supplier ?? 0);

            $items[] = [
                'goods_receipt_item_id' => (int) $item->id,
                'purchase_order_item_id' => $item->purchase_order_item_id ? (int) $item->purchase_order_item_id : null,
                'product_variant_id' => (int) $item->product_variant_id,
                'product_name' => (string) $item->product_name,
                'sku' => (string) $item->sku,
                'color_raw' => $item->color_raw !== null ? (string) $item->color_raw : null,
                'color' => $item->color !== null ? (string) $item->color : null,
                'qty_receivable' => $receivable,
                'unit_price' => $doPrice,
                'tax_id' => $item->purchaseOrderItem?->tax_id ? (int) $item->purchaseOrderItem->tax_id : null,
            ];
        }

        // Pembebanan hutang di HO: branch faktur = branch PO (HQ), bukan
        // branch fisik GRN (cabang). No. faktur supplier ikut DO (kolom GRN).
        $invoiceBranchId = $po ? (int) $po->branch_id : (int) $grn->branch_id;
        $supplierInvoiceNo = $grn->supplier_invoice_no;

        return [
            'id' => (int) $grn->id,
            'number' => (string) $grn->number,
            'branch_id' => $invoiceBranchId,
            'supplier_id' => (int) $grn->supplier_id,
            'purchase_order_id' => (int) $grn->purchase_order_id,
            'supplier_invoice_no' => $supplierInvoiceNo !== null ? (string) $supplierInvoiceNo : null,
            'is_tax_inclusive' => (bool) ($po->is_tax_inclusive ?? false),
            'due_date' => $po->due_date ? (string) $po->due_date : null,
            'items' => $items,
        ];
    }
}
