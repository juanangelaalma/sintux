<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Company\Application\CompanyAccess;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Purchasing\Models\PurchaseOrderItem;
use Modules\Warehouse\Application\StockLayer\ReceivePurchaseStock;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;

class ApproveGoodsReceipt
{
    public function __construct(
        private readonly ReceivePurchaseStock $receivePurchaseStock,
        private readonly TransferApprovedGrn $transferApprovedGrn,
        private readonly GetWarehouses $warehouses,
    ) {}

    /**
     * Approval = alokasi PO + posting stok HO + transfer HO→cabang, satu alur
     * synchronous. Kegagalan transfer tidak membatalkan approval (stok
     * tetap aman di HO, `transfer_error` tercatat untuk retry manual).
     */
    public function execute(int $goodsReceiptId, int $userId): GoodsReceipt
    {
        $grn = DB::transaction(function () use ($goodsReceiptId, $userId) {
            // Kunci baris agar dua approval konkuren tidak lolos
            // pengecekan status dan memposting stok ganda.
            $grn = GoodsReceipt::with(['items'])
                ->lockForUpdate()
                ->findOrFail($goodsReceiptId);

            if (! $grn->status->canDecide()) {
                throw ValidationException::withMessages([
                    'grn' => "Penerimaan sudah {$grn->status->label()}.",
                ]);
            }

            // Kunci baris PO agar alokasi slot dibaca dari data terkini
            // dan dua approval untuk PO yang sama saling antre.
            $poItems = PurchaseOrderItem::where('purchase_order_id', $grn->purchase_order_id)
                ->lockForUpdate()
                ->get();

            $unmapped = $grn->items->reject(fn ($item) => $item->isMapped())->count();

            if ($unmapped > 0) {
                throw ValidationException::withMessages([
                    'grn' => "Masih ada {$unmapped} barang belum di-mapping.",
                ]);
            }

            $hoWarehouseId = $this->resolveHoWarehouse();

            // Alokasikan qty tiap warna ke baris PO (sisa qty) untuk cabang ini.
            // $taken mencatat pemakaian slot di memori agar warna berikutnya
            // tidak mengalokasi dari slot penuh yang sama (over-receipt).
            $taken = [];
            foreach ($grn->items as $item) {
                $qty = (float) $item->qty_received;

                if ($qty <= 0) {
                    continue;
                }

                // Alokasi by SKU (bukan variant): warna hasil mapping
                // cabang belum tentu terdaftar sebagai baris PO.
                $remaining = $qty;
                $candidates = $poItems
                    ->where('sku', $item->sku)
                    ->where('destination_branch_id', (int) $grn->branch_id)
                    ->sortBy('id');

                if ($candidates->isEmpty()) {
                    throw ValidationException::withMessages([
                        'grn' => "Tidak ada baris PO untuk SKU {$item->sku} yang dialokasikan ke cabang ini. Periksa kode produk di PO.",
                    ]);
                }

                foreach ($candidates as $poItem) {
                    if ($remaining <= 0.0001) {
                        break;
                    }

                    $slot = (float) $poItem->qty_ordered
                        - (float) $poItem->qty_received
                        - ($taken[$poItem->id] ?? 0.0);

                    if ($slot <= 0.0001) {
                        continue;
                    }

                    $take = min($slot, $remaining);
                    $taken[$poItem->id] = ($taken[$poItem->id] ?? 0.0) + $take;
                    $remaining -= $take;

                    // Baris warna pertama menempati slot; warna berikutnya
                    // (sku sama) menempati slot berikutnya.
                    if ($item->purchase_order_item_id === null) {
                        $item->purchase_order_item_id = $poItem->id;
                    }
                }

                if ($remaining > 0.0001) {
                    throw ValidationException::withMessages([
                        'grn' => "Qty {$item->sku} ({$item->color_raw}) melebihi sisa PO.",
                    ]);
                }
            }

            $receivable = $grn->items->filter(fn ($item) => (float) $item->qty_received > 0);

            if ($receivable->isEmpty()) {
                throw ValidationException::withMessages([
                    'grn' => 'Tidak ada qty diterima untuk diposting.',
                ]);
            }

            // 1. Tautkan baris PO lalu posting stok ke gudang HO.
            // Biaya stok = snapshot harga DO di GRN item. Satu-satunya
            // sumber kebenaran; tidak ada fallback ke harga PO.
            foreach ($receivable as $item) {
                $unitCost = (float) ($item->unit_price_supplier ?? 0);

                $this->receivePurchaseStock->execute([
                    'warehouse_id' => $hoWarehouseId,
                    'product_variant_id' => (int) $item->product_variant_id,
                    'qty' => (float) $item->qty_received,
                    'unit_cost' => $unitCost,
                    'received_at' => $grn->receipt_date,
                    'source_type' => 'purchase_order',
                    'source_id' => (int) $grn->purchase_order_id,
                    'reference_type' => 'Modules\Purchasing\Models\GoodsReceipt',
                ]);

                $item->update([
                    'purchase_order_item_id' => $item->purchase_order_item_id,
                ]);

                // 2. Increment qty_received on purchase_order_item
                if ($item->purchase_order_item_id) {
                    PurchaseOrderItem::where('id', $item->purchase_order_item_id)
                        ->increment('qty_received', (float) $item->qty_received);
                }
            }

            // 3. Update PO status: received if all fulfilled, partially_received otherwise
            $poId = $grn->purchase_order_id;
            $unfulfilled = PurchaseOrderItem::where('purchase_order_id', $poId)
                ->whereRaw('qty_received < qty_ordered')
                ->count();

            $anyReceived = PurchaseOrderItem::where('purchase_order_id', $poId)
                ->where('qty_received', '>', 0)
                ->exists();

            $po = PurchaseOrder::find($poId);

            if ($po && $unfulfilled === 0) {
                $po->update(['status' => PurchaseOrderStatus::Received]);
            } elseif ($po && $anyReceived && in_array($po->status, [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived], true)) {
                $po->update(['status' => PurchaseOrderStatus::PartiallyReceived]);
            }

            // 4. Update GRN status to approved
            $branchCode = CompanyAccess::branchCode((int) $grn->branch_id) ?? '';

            $grn->update([
                'status' => GoodsReceiptStatus::Approved,
                'warehouse_id' => $hoWarehouseId,
                'decided_by' => $userId,
                'decided_at' => now(),
                'note' => $grn->note ?? "Approval GRN {$grn->number} ({$grn->supplier_do_no}) cabang {$branchCode}.",
            ]);

            return $grn->fresh(['items']);
        });

        $this->transferApprovedGrn->execute($grn->id, $userId);

        return $grn->fresh(['items']);
    }

    private function resolveHoWarehouse(): int
    {
        $hqBranchId = CompanyAccess::headquartersBranchId();

        $warehouseId = $hqBranchId === null
            ? null
            : $this->warehouses->defaultRegularWarehouseId($hqBranchId);

        if ($warehouseId === null) {
            throw ValidationException::withMessages([
                'grn' => 'Gudang Regular HO tidak ditemukan.',
            ]);
        }

        return $warehouseId;
    }
}
