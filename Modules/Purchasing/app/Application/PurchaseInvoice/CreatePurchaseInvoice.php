<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\GetPurchaseTaxes;
use Modules\Approval\Application\ApprovalEngine;
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
        private readonly GetPurchaseTaxes $getPurchaseTaxes,
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
        $taxes = collect($this->getPurchaseTaxes->execute())->keyBy('id');
        $creatorId = $userId ?? (int) auth()->id();
        $creatorName = $userName ?? auth()->user()?->name;

        return DB::transaction(function () use ($data, $branchCode, $variants, $taxes, $creatorId, $creatorName) {
            $grn = $this->resolveGrn($data);
            $purchaseOrderId = $this->resolvePurchaseOrderId($data, $grn);
            $isTaxInclusive = (bool) ($data['is_tax_inclusive'] ?? false);

            $lines = $this->validateLines($data['items'], $grn, $purchaseOrderId, $taxes, $variants);

            [$lines, $subtotal, $taxAmount] = $this->computeTotals($lines, $isTaxInclusive);
            $total = $subtotal + $taxAmount;

            $number = $this->nextNumber((int) $data['branch_id'], (string) $branchCode, (string) $data['invoice_date']);

            $inv = PurchaseInvoice::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $purchaseOrderId,
                'goods_receipt_id' => $grn?->id,
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
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'tax_id' => $line['tax_id'],
                    'tax_rate' => $line['tax_rate'],
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

        if ($grn->status !== GoodsReceiptStatus::Posted->value) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => 'Faktur hanya dapat dibuat dari penerimaan barang yang sudah diposting.',
            ]);
        }

        if ((int) $grn->branch_id !== (int) $data['branch_id']) {
            throw ValidationException::withMessages([
                'goods_receipt_id' => 'Cabang penerimaan barang tidak sama dengan cabang faktur.',
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
     * Validasi + normalisasi baris: konsistensi PO/GRN dan harga dikunci
     * ikut PO. Batas kumulatif dicek terpusat agar sama dengan finalize.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{goods_receipt_item_id: int|null, purchase_order_item_id: int|null, product_variant_id: int, qty: float, unit_price: float, tax_id: int|null, tax_rate: float, line_total: float, product_name: string}>
     */
    private function validateLines(array $items, ?object $grn, ?int $purchaseOrderId, mixed $taxes, mixed $variants): array
    {
        $lines = [];

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

            // Harga dikunci mengikuti PO untuk baris ber-PO.
            $unitPrice = (float) $item['unit_price'];

            if ($poItem && round($unitPrice, 4) !== round((float) $poItem->unit_price, 4)) {
                throw ValidationException::withMessages([
                    "items.{$index}.unit_price" => "Harga harus sama dengan harga PO ({$poItem->unit_price}).",
                ]);
            }

            $grnItem = $grnItemId ? DB::table('goods_receipt_items')->where('id', $grnItemId)->first() : null;

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
            }

            $variant = $variants->get((int) $item['product_variant_id']);
            $taxId = $item['tax_id'] ?? null;
            $taxRate = $taxId ? (float) ($taxes->get($taxId)['rate'] ?? 0) : 0.0;

            $lines[] = [
                'index' => $index,
                'goods_receipt_item_id' => $grnItemId,
                'purchase_order_item_id' => $poItemId,
                'product_variant_id' => (int) $item['product_variant_id'],
                'product_name' => $variant['product_name'] ?? '',
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'tax_id' => $taxId ? (int) $taxId : null,
                'tax_rate' => $taxRate,
                'line_total' => 0.0,
            ];
        }

        $this->validateInvoiceQuantities->execute($lines, 'items');

        return $lines;
    }

    /**
     * @param  list<array{qty: float, unit_price: float, tax_rate: float}>  $lines
     * @return array{0: list<array<string, mixed>>, 1: float, 2: float}
     */
    private function computeTotals(array $lines, bool $isTaxInclusive): array
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;

        foreach ($lines as $i => $line) {
            $lineSubtotal = $line['qty'] * $line['unit_price'];

            if ($isTaxInclusive && $line['tax_rate'] > 0) {
                $gross = $lineSubtotal;
                $lineSubtotal = $gross / (1 + ($line['tax_rate'] / 100));
                $lineTax = $gross - $lineSubtotal;
                $lines[$i]['line_total'] = $gross;
            } else {
                $lineTax = $lineSubtotal * ($line['tax_rate'] / 100);
                $lines[$i]['line_total'] = $lineSubtotal + $lineTax;
            }

            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax;
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
