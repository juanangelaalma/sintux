<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Company\Application\CompanyAccess;
use Modules\Product\Application\Variant\EnsureMappedVariant;
use Modules\Product\Application\Variant\FindVariantBySkuAndColor;
use Modules\Product\Application\Variant\FindVariantSummary;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Infrastructure\External\SupplierDoClient;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Purchasing\Models\GoodsReceiptItem;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;

class FetchGoodsReceipt
{
    public function __construct(
        private readonly SupplierDoClient $supplierDo,
        private readonly FindVariantSummary $variantSummary,
        private readonly EnsureMappedVariant $ensureMappedVariant,
        private readonly GetWarehouses $warehouses,
    ) {}

    /**
     * Preview DO supplier TANPA menyimpan apa pun. Dipakai form fetch untuk
     * menampilkan data sebelum user menekan "Simpan sebagai Draft".
     *
     * @return array{existing_id: int|null}|array{existing_id: null, po: PurchaseOrder, payload: array, items: list<array{supplier_barcode: string, product_name: string, sku: string, size: string|null, uom_name: string|null, color_raw: string|null, color: string, qty_do: float, unit_price_supplier: float, product_variant_id: int|null}>}
     */
    public function preview(
        string $doNo,
        int $branchId,
        ?int $purchaseOrderId = null,
        ?string $customer = null
    ): array {
        $doNo = trim($doNo);

        $existing = GoodsReceipt::where('supplier_do_no', $doNo)->first();

        if ($existing) {
            if ((int) $existing->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'do_no' => "{$doNo} sudah di-fetch oleh cabang lain.",
                ]);
            }

            return ['existing_id' => (int) $existing->id];
        }

        $data = $this->loadPreview($doNo, $branchId, $purchaseOrderId, $customer);

        return ['existing_id' => null] + $data;
    }

    /**
     * Simpan DO sebagai GRN draft (dipanggil tombol "Simpan sebagai Draft").
     */
    public function execute(
        string $doNo,
        int $branchId,
        string $branchCode,
        ?int $purchaseOrderId = null,
        ?string $customer = null
    ): GoodsReceipt {
        $doNo = trim($doNo);

        $existing = GoodsReceipt::with(['items'])->where('supplier_do_no', $doNo)->first();

        if ($existing) {
            if ((int) $existing->branch_id !== $branchId) {
                throw ValidationException::withMessages([
                    'do_no' => "{$doNo} sudah di-fetch oleh cabang lain.",
                ]);
            }

            return $existing;
        }

        $data = $this->loadPreview($doNo, $branchId, $purchaseOrderId, $customer);
        $po = $data['po'];
        $payload = $data['payload'];

        return DB::transaction(function () use ($payload, $po, $data, $branchId, $branchCode) {
            // Binding pertama menang: cabang tanpa mapping mengikat cust_name
            // DO; cabang yang sudah ter-mapping wajib cocok, dan nama yang
            // sudah dipakai cabang lain selalu ditolak.
            if (trim((string) ($payload['cust_name'] ?? '')) !== '') {
                CompanyAccess::bindSupplierCustomerName($branchId, (string) $payload['cust_name']);
            }

            $sequence = GoodsReceipt::where('branch_id', $branchId)->count() + 1;
            $number = 'GRN-'.$branchCode.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            $grn = GoodsReceipt::create([
                'number' => $number,
                'branch_id' => $branchId,
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'warehouse_id' => $this->resolveHoWarehouse(),
                'supplier_do_no' => $payload['do_no'],
                'supplier_invoice_no' => $payload['inv_no'],
                'po_no' => $payload['po_no'],
                'do_date' => $payload['do_date'],
                'cust_name' => $payload['cust_name'],
                'driver' => $payload['driver'],
                'nopol' => $payload['nopol'],
                'transaction_type' => $payload['transaction_type'],
                'status' => GoodsReceiptStatus::Draft,
                'receipt_date' => $payload['do_date'] ?? now()->toDateString(),
                'raw_payload' => $payload,
            ]);

            foreach ($data['items'] as $previewItem) {
                $variantId = $previewItem['product_variant_id'];

                // Auto-mapping: varian yang belum ada langsung dibuatkan
                // (kategori/satuan kosong, dilengkapi di modul Produk).
                if ($variantId === null) {
                    $variantId = $this->ensureMappedVariant->execute([
                        'owner_branch_id' => (int) $po->branch_id,
                        'sku' => $previewItem['sku'],
                        'color' => $previewItem['color_raw'],
                        'product_name' => $previewItem['product_name'],
                        'purchase_price' => (float) $previewItem['unit_price_supplier'],
                        'supplier_do_no' => $payload['do_no'],
                    ])->id;
                }

                $grn->items()->create([
                    'supplier_barcode' => $previewItem['supplier_barcode'],
                    'product_variant_id' => $variantId,
                    'product_name' => $previewItem['product_name'],
                    'sku' => $previewItem['sku'],
                    'uom_name' => $previewItem['uom_name'],
                    'size' => $previewItem['size'],
                    'color_raw' => $previewItem['color_raw'],
                    'color' => $previewItem['color'],
                    'qty_do' => $previewItem['qty_do'],
                    'qty_received' => $previewItem['qty_do'],
                    'unit_price_supplier' => $previewItem['unit_price_supplier'],
                    'verification_status' => GoodsReceiptItem::VERIFY_NOT_VERIFIED,
                ]);
            }

            return $grn->load(['items']);
        });
    }

    /**
     * Ambil + validasi DO dari supplier tanpa menulis DB apa pun.
     *
     * @return array{po: PurchaseOrder, payload: array, items: list<array{supplier_barcode: string, product_name: string, sku: string, size: string|null, uom_name: string|null, color_raw: string|null, color: string, qty_do: float, unit_price_supplier: float, product_variant_id: int|null}>}
     */
    private function loadPreview(
        string $doNo,
        int $branchId,
        ?int $purchaseOrderId,
        ?string $customer
    ): array {
        // Customer supplier diisi dari form fetch atau mapping cabang.
        $customer = trim((string) ($customer ?? ''));

        if ($customer === '') {
            $customer = CompanyAccess::supplierCustomerName($branchId) ?? '';
        }

        $payload = $this->supplierDo->fetchByDoNo($doNo, $customer !== '' ? $customer : null);

        $po = $this->resolvePurchaseOrder($payload, $purchaseOrderId, $doNo);

        if (! in_array($po->status, [PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived], true)) {
            throw ValidationException::withMessages([
                'do_no' => "{$po->number} belum terkirim (status: {$po->status->label()}).",
            ]);
        }

        $hasAllocation = DB::table('purchase_order_items')
            ->where('purchase_order_id', $po->id)
            ->where('destination_branch_id', $branchId)
            ->exists();

        if (! $hasAllocation) {
            throw ValidationException::withMessages([
                'do_no' => 'DO ini tidak memiliki alokasi untuk cabang Anda.',
            ]);
        }

        $items = [];
        foreach ($payload['products'] as $product) {
            // Produk tanpa rincian warna = satu bundle utuh (tanpa pecah warna).
            if (empty($product['details'])) {
                $product['details'] = [['color' => null, 'qty' => $product['qty']]];
            }

            $detailQty = collect($product['details'])->sum(fn ($d) => (float) $d['qty']);

            if (abs($detailQty - (float) $product['qty']) > 0.0001) {
                throw ValidationException::withMessages([
                    'do_no' => "Rincian warna barcode {$product['barcode']} tidak sama dengan qty-nya.",
                ]);
            }

            // Satu putaran query per warna: id + satuan diambil sekaligus.
            $uomName = null;
            foreach ($product['details'] as $detail) {
                $found = $this->variantSummary->execute(
                    $product['prd_code'], $detail['color'], (int) $po->branch_id
                );

                if ($uomName === null && ($found['uom_name'] ?? null)) {
                    $uomName = $found['uom_name'];
                }

                $items[] = [
                    'supplier_barcode' => $product['barcode'],
                    'product_name' => $product['prd_name'],
                    'sku' => $product['prd_code'],
                    'size' => $product['size'] ?? null,
                    'uom_name' => $uomName,
                    'color_raw' => $detail['color'],
                    'color' => FindVariantBySkuAndColor::normalizeColor($detail['color']),
                    'qty_do' => (float) $detail['qty'],
                    'unit_price_supplier' => (float) $product['price'],
                    'product_variant_id' => $found['id'] ?? null,
                ];
            }
        }

        return ['po' => $po, 'payload' => $payload, 'items' => $items];
    }

    /**
     * @param  array{po_no: string|null}  $payload
     */
    private function resolvePurchaseOrder(array $payload, ?int $purchaseOrderId, string $doNo): PurchaseOrder
    {
        if ($payload['po_no'] !== null) {
            $po = PurchaseOrder::where('number', $payload['po_no'])->first();

            if (! $po) {
                throw ValidationException::withMessages([
                    'do_no' => "{$payload['po_no']} dari DO ini tidak ada di sistem.",
                ]);
            }

            if ($purchaseOrderId !== null && (int) $po->id !== $purchaseOrderId) {
                throw ValidationException::withMessages([
                    'purchase_order_id' => "DO ini untuk {$payload['po_no']}, bukan PO yang dipilih.",
                ]);
            }

            return $po;
        }

        // Supplier belum mencantumkan po_no → cabang memilih PO manual.
        if ($purchaseOrderId === null) {
            throw ValidationException::withMessages([
                'purchase_order_id' => "{$doNo} belum mencantumkan nomor PO, pilih PO secara manual.",
            ]);
        }

        $po = PurchaseOrder::find($purchaseOrderId);

        if (! $po) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'PO yang dipilih tidak ditemukan.',
            ]);
        }

        return $po;
    }

    private function resolveHoWarehouse(): int
    {
        $hqBranchId = CompanyAccess::headquartersBranchId();

        $warehouseId = $hqBranchId === null
            ? null
            : $this->warehouses->defaultRegularWarehouseId($hqBranchId);

        if ($warehouseId === null) {
            throw ValidationException::withMessages([
                'do_no' => 'Gudang Regular HO tidak ditemukan.',
            ]);
        }

        return $warehouseId;
    }
}
