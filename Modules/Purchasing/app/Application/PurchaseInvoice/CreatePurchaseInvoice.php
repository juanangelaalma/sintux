<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\TaxCalculator;
use Modules\Accounting\Application\TaxQuery;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Company\Application\CompanyAccess;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Application\PurchaseOrder\MarkPurchaseOrderClosed;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Models\PurchaseInvoice;

class CreatePurchaseInvoice
{
    /**
     * Sufiks tipe barang pada nomor faktur. PO selalu menghasilkan
     * barang regular, sehingga faktur ber-PO/GRN selalu 'A'.
     */
    public const GOODS_TYPE_REGULAR = 'A';

    public function __construct(
        private readonly GetPurchaseVariants $purchaseVariants,
        private readonly TaxQuery $taxQuery,
        private readonly TaxCalculator $taxCalculator,
        private readonly ApprovalEngine $approvalEngine,
        private readonly ValidateInvoiceQuantities $validateInvoiceQuantities,
        private readonly IncrementInvoicedQuantities $incrementInvoicedQuantities,
        private readonly MarkPurchaseOrderClosed $markPurchaseOrderClosed,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $branchIds  Branch scope for variant resolution. Null = all.
     */
    public function execute(array $data, string $branchCode, ?int $userId = null, ?string $userName = null, ?array $branchIds = null): PurchaseInvoice
    {
        $variants = collect($this->purchaseVariants->execute($branchIds))->keyBy('id');
        $taxes = collect($this->taxQuery->listForPurchase())->keyBy('id');
        $creatorId = $userId ?? (int) auth()->id();
        $creatorName = $userName ?? auth()->user()?->name;

        return DB::transaction(function () use ($data, $branchCode, $variants, $taxes, $creatorId, $creatorName) {
            // Pembebanan hutang selalu di HO (PO milik HO). Faktur cabang ditolak.
            $hqBranchId = CompanyAccess::headquartersBranchId();
            if ($hqBranchId !== null && (int) $data['branch_id'] !== $hqBranchId) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Faktur pembelian hanya dapat dibuat untuk Head Office (pembebanan hutang di HO).',
                ]);
            }

            $grn = $this->resolveGrn($data);
            $purchaseOrderId = $this->resolvePurchaseOrderId($data, $grn);
            // Mode pajak faktur ber-GRN mengikuti PO (DO tidak bawa info pajak).
            $isTaxInclusive = (bool) ($data['is_tax_inclusive'] ?? false);
            if ($grn && $purchaseOrderId) {
                $poRow = DB::table('purchase_orders')->where('id', $purchaseOrderId)->first();
                if ($poRow) {
                    $isTaxInclusive = (bool) $poRow->is_tax_inclusive;
                }
            }

            $lines = $this->validateLines($data['items'], $grn, $purchaseOrderId, $taxes, $variants);

            [$lines, $subtotal, $taxAmount] = $this->computeTotals($lines, $isTaxInclusive);
            $total = $subtotal + $taxAmount;

            $supplierInvoiceNo = $this->resolveSupplierInvoiceNo($data, $grn);
            $taxInvoiceNo = $this->normalizeNo($data['tax_invoice_no'] ?? null);
            $this->assertUniqueInvoiceNos((int) $data['supplier_id'], $supplierInvoiceNo, $taxInvoiceNo);

            $number = $this->nextNumber((int) $data['branch_id'], (string) $branchCode, (string) $data['invoice_date']);

            $inv = PurchaseInvoice::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $purchaseOrderId,
                'goods_receipt_id' => $grn?->id,
                'supplier_invoice_no' => $supplierInvoiceNo,
                'tax_invoice_no' => $taxInvoiceNo,
                'status' => PurchaseInvoiceStatus::Pending,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'note' => $data['note'] ?? null,
                'currency_code' => 'IDR',
                'is_tax_inclusive' => $isTaxInclusive,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            foreach ($lines as $line) {
                $variant = $variants->get($line['product_variant_id']);

                $inv->items()->create([
                    'purchase_order_item_id' => $line['purchase_order_item_id'],
                    'goods_receipt_item_id' => $line['goods_receipt_item_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'product_name' => $variant['product_name'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'uom_name' => $variant['uom_name'] ?? null,
                    'color_raw' => $line['color_raw'] ?? null,
                    'color' => $line['color'] ?? null,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'tax_id' => $line['tax_id'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_breakdown' => $line['tax_breakdown'],
                    'line_total' => $line['line_total'],
                ]);
            }

            $mapping = $this->approvalEngine->evaluateAndMap([
                'transaction_type' => 'purchase_invoice',
                'transaction_id' => $inv->id,
                'document_number' => $inv->number,
                'created_by' => $creatorId,
                'created_by_name' => $creatorName,
                'branch_id' => $inv->branch_id,
                'total' => $total,
                'currency_code' => 'IDR',
            ]);

            if (! $mapping) {
                // Auto-final: counter naik + status approved dalam transaksi yang sama.
                $this->incrementInvoicedQuantities->execute($lines);

                if ($purchaseOrderId) {
                    $this->markPurchaseOrderClosed->execute($purchaseOrderId);
                }

                $inv->update(['status' => PurchaseInvoiceStatus::Approved]);
            }

            return $inv->load('items');
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveGrn(array $data): ?object
    {
        if (empty($data['goods_receipt_id'])) {
            return null;
        }

        $grn = DB::table('goods_receipts')->where('id', (int) $data['goods_receipt_id'])->first();

        if (! $grn) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => 'Penerimaan barang tidak ditemukan.',
            ]);
        }

        if ($grn->status !== GoodsReceiptStatus::Approved->value) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => 'Faktur hanya dapat dibuat dari penerimaan barang yang sudah disetujui HO.',
            ]);
        }

        // Strict 1:1 — satu GRN tepat satu faktur.
        $exists = DB::table('purchase_invoices')
            ->where('goods_receipt_id', (int) $grn->id)
            ->exists();
        if ($exists) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => 'Penerimaan barang ini sudah memiliki faktur.',
            ]);
        }

        // Faktur HO boleh menagih GRN cabang mana pun, asal PO-nya milik HO
        // yang sama dan supplier sama. GRN branch (cabang fisik) tidak harus
        // sama dengan invoice branch (HO pembebanan).
        $po = DB::table('purchase_orders')->where('id', (int) $grn->purchase_order_id)->first();
        if ($po && (int) $po->branch_id !== (int) $data['branch_id']) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => 'PO penerimaan barang bukan milik HO faktur ini.',
            ]);
        }

        if ((int) $grn->supplier_id !== (int) $data['supplier_id']) {
            throw ValidationException::withMessages([
                'supplier_id' => 'Supplier faktur tidak sama dengan supplier penerimaan barang.',
            ]);
        }

        if (! empty($data['purchase_order_id']) && (int) $data['purchase_order_id'] !== (int) $grn->purchase_order_id) {
            throw ValidationException::withMessages([
                'purchase_order_id' => 'PO faktur tidak sama dengan PO penerimaan barang.',
            ]);
        }

        return $grn;
    }

    private function resolvePurchaseOrderId(array $data, ?object $grn): ?int
    {
        if (! empty($data['purchase_order_id'])) {
            return (int) $data['purchase_order_id'];
        }

        if ($grn) {
            return (int) $grn->purchase_order_id;
        }

        return null;
    }

    /**
     * No. faktur dagang supplier: wajib sama dengan DO (kolom GRN)
     * bila GRN-nya punya nomor; auto-isi bila form kosong.
     */
    private function resolveSupplierInvoiceNo(array $data, ?object $grn): ?string
    {
        $input = $this->normalizeNo($data['supplier_invoice_no'] ?? null);

        if (! $grn) {
            return $input;
        }

        $grnNo = $this->normalizeNo($grn->supplier_invoice_no ?? null);

        if ($grnNo === null) {
            return $input;
        }

        if ($input === null) {
            return $grnNo;
        }

        if ($input !== $grnNo) {
            throw ValidationException::withMessages([
                'supplier_invoice_no' => "No. faktur supplier harus sama dengan DO ({$grnNo}).",
            ]);
        }

        return $input;
    }

    private function normalizeNo(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : mb_substr($trimmed, 0, 100);
    }

    /**
     * Cegah faktur ganda per supplier. DB unique index adalah pengaman
     * akhir; cek ini memberi pesan yang jelas + mencakup transaksi ini.
     */
    private function assertUniqueInvoiceNos(int $supplierId, ?string $supplierInvoiceNo, ?string $taxInvoiceNo): void
    {
        if ($supplierInvoiceNo !== null && DB::table('purchase_invoices')
            ->where('supplier_id', $supplierId)
            ->where('supplier_invoice_no', $supplierInvoiceNo)
            ->exists()) {
            throw ValidationException::withMessages([
                'supplier_invoice_no' => 'No. faktur supplier ini sudah pernah dicatat untuk supplier ini.',
            ]);
        }

        if ($taxInvoiceNo !== null && DB::table('purchase_invoices')
            ->where('supplier_id', $supplierId)
            ->where('tax_invoice_no', $taxInvoiceNo)
            ->exists()) {
            throw ValidationException::withMessages([
                'tax_invoice_no' => 'No. faktur pajak ini sudah pernah dicatat untuk supplier ini.',
            ]);
        }
    }

    /**
     * Validasi + normalisasi baris:
     * - Mode GRN (strict 1:1): qty = received persis, harga = DO persis,
     *   pajak ikut PO, semua item GRN wajib ter-cover tepat sekali.
     * - Mode manual (tanpa GRN, kompatibilitas lama): harga bebas,
     *   batas kumulatif dicek terpusat agar sama dengan finalize.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{goods_receipt_item_id: int|null, purchase_order_item_id: int|null, product_variant_id: int, qty: float, unit_price: float, tax_id: int|null, tax_rate: float, tax_breakdown: list<array{tax_id: int, rate: float, amount: float}>|null, line_total: float, product_name: string, color_raw: string|null, color: string|null}>
     */
    private function validateLines(array $items, ?object $grn, ?int $purchaseOrderId, mixed $taxes, mixed $variants): array
    {
        $lines = [];

        if ($grn) {
            $grnItems = DB::table('goods_receipt_items')->where('goods_receipt_id', (int) $grn->id)->get()->keyBy('id');
            if ($grnItems->isEmpty()) {
                throw ValidationException::withMessages([
                    'goods_receipt_id' => 'Penerimaan barang ini tidak memiliki item.',
                ]);
            }
            if (count($items) !== $grnItems->count()) {
                throw ValidationException::withMessages([
                    'items' => 'Jumlah baris faktur harus sama dengan jumlah baris penerimaan ('.$grnItems->count().' baris).',
                ]);
            }
            $seen = [];
        }

        foreach ($items as $index => $item) {
            $qty = (float) $item['qty'];
            $poItemId = ! empty($item['purchase_order_item_id']) ? (int) $item['purchase_order_item_id'] : null;
            $grnItemId = ! empty($item['goods_receipt_item_id']) ? (int) $item['goods_receipt_item_id'] : null;

            $poItem = $poItemId ? DB::table('purchase_order_items')->where('id', $poItemId)->first() : null;

            if ($poItemId && ! $poItem) {
                throw ValidationException::withMessages([
                    "items.{$index}.purchase_order_item_id" => 'Item PO tidak ditemukan.',
                ]);
            }

            if ($poItem && $purchaseOrderId && (int) $poItem->purchase_order_id !== $purchaseOrderId) {
                throw ValidationException::withMessages([
                    "items.{$index}.purchase_order_item_id" => 'Item PO bukan bagian dari PO faktur ini.',
                ]);
            }

            $grnItem = $grnItemId ? DB::table('goods_receipt_items')->where('id', $grnItemId)->first() : null;

            if ($grn && ! $grnItem) {
                throw ValidationException::withMessages([
                    "items.{$index}.goods_receipt_item_id" => 'Item penerimaan wajib diisi untuk faktur ber-GRN.',
                ]);
            }

            if ($grnItemId && ! $grnItem) {
                throw ValidationException::withMessages([
                    "items.{$index}.goods_receipt_item_id" => 'Item penerimaan barang tidak ditemukan.',
                ]);
            }

            if ($grnItem) {
                if (! $grn || (int) $grnItem->goods_receipt_id !== (int) $grn->id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.goods_receipt_item_id" => 'Item penerimaan bukan bagian dari GRN faktur ini.',
                    ]);
                }

                if ($poItemId && $grnItem->purchase_order_item_id && (int) $grnItem->purchase_order_item_id !== $poItemId) {
                    throw ValidationException::withMessages([
                        "items.{$index}.goods_receipt_item_id" => 'Item penerimaan tidak cocok dengan item PO.',
                    ]);
                }

                if (isset($seen[$grnItemId])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.goods_receipt_item_id" => 'Item penerimaan duplikat dalam faktur ini.',
                    ]);
                }
                $seen[$grnItemId] = true;

                // Qty strict = received persis.
                if (abs($qty - (float) $grnItem->qty_received) > 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$index}.qty" => 'Qty harus sama dengan yang diterima ('.$grnItem->qty_received.').',
                    ]);
                }

                // Harga strict = DO supplier (snapshot GRN). Satu-satunya
                // sumber kebenaran; tidak ada fallback ke harga PO.
                $expectedPrice = (float) ($grnItem->unit_price_supplier ?? 0);
                $unitPrice = (float) $item['unit_price'];
                if (round($unitPrice, 4) !== round($expectedPrice, 4)) {
                    throw ValidationException::withMessages([
                        "items.{$index}.unit_price" => "Harga harus sama dengan harga DO ({$expectedPrice}).",
                    ]);
                }

                // Varian tidak boleh ditukar.
                if ((int) $item['product_variant_id'] !== (int) $grnItem->product_variant_id) {
                    throw ValidationException::withMessages([
                        "items.{$index}.product_variant_id" => 'Varian harus sama dengan baris penerimaan.',
                    ]);
                }

                // Pajak ikut PO item (DO tidak bawa info pajak).
                $poTaxId = $poItem && $poItem->tax_id ? (int) $poItem->tax_id : null;
                if ($poTaxId !== null && (int) ($item['tax_id'] ?? 0) !== $poTaxId) {
                    throw ValidationException::withMessages([
                        "items.{$index}.tax_id" => 'Pajak harus mengikuti PO.',
                    ]);
                }
            } else {
                // Mode manual: harga bebas.
                $unitPrice = (float) $item['unit_price'];
            }

            $variant = $variants->get((int) $item['product_variant_id']);
            $taxId = $item['tax_id'] ?? null;
            $taxDef = $taxId ? $taxes->get($taxId) : null;
            $taxRate = $taxDef ? (float) ($taxDef['rate'] ?? 0) : 0.0;

            $lines[] = [
                'index' => $index,
                'goods_receipt_item_id' => $grnItemId,
                'purchase_order_item_id' => $poItemId,
                'product_variant_id' => (int) $item['product_variant_id'],
                'product_name' => $variant['product_name'] ?? '',
                'color_raw' => $grnItem->color_raw ?? null,
                'color' => $grnItem->color ?? null,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'tax_id' => $taxId ? (int) $taxId : null,
                'tax_rate' => $taxRate,
                'tax_def' => $taxDef,
                'tax_breakdown' => null,
                'line_total' => 0.0,
            ];
        }

        $this->validateInvoiceQuantities->execute($lines, 'items');

        return $lines;
    }

    /**
     * @param  list<array{qty: float, unit_price: float, tax_rate: float, tax_def: array<string, mixed>|null}>  $lines
     * @return array{0: list<array<string, mixed>>, 1: float, 2: float}
     */
    private function computeTotals(array $lines, bool $isTaxInclusive): array
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;

        foreach ($lines as $i => $line) {
            $lineGross = $line['qty'] * $line['unit_price'];
            $result = $this->taxCalculator->calculate($lineGross, $line['tax_def'] ?? null, $isTaxInclusive);

            if ($isTaxInclusive) {
                $lineSubtotal = $lineGross - $result['total'];
                $lines[$i]['line_total'] = $lineGross;
            } else {
                $lineSubtotal = $lineGross;
                $lines[$i]['line_total'] = $lineGross + $result['total'];
            }

            $lines[$i]['tax_breakdown'] = $result['breakdown'] === [] ? null : $result['breakdown'];
            unset($lines[$i]['tax_def']);

            $subtotal += $lineSubtotal;
            $taxAmount += $result['total'];
        }

        return [$lines, $subtotal, $taxAmount];
    }

    /**
     * Nomor FBL/{cabang}/YYYYMMDD/XXX/A. XXX urut per cabang per tanggal
     * faktur; A = barang regular. Dikunci seperti PO anti duplikat.
     */
    private function nextNumber(int $branchId, string $branchCode, string $invoiceDate): string
    {
        $dateObj = Carbon::parse($invoiceDate);
        $datePart = $dateObj->format('Ymd');

        $lockedIds = PurchaseInvoice::where('branch_id', $branchId)
            ->whereDate('invoice_date', $dateObj->toDateString())
            ->lockForUpdate()
            ->pluck('id');

        $sequence = $lockedIds->count() + 1;

        return sprintf('FBL/%s/%s/%03d/%s', $branchCode, $datePart, $sequence, self::GOODS_TYPE_REGULAR);
    }
}
