<?php

namespace Modules\Purchasing\Application\PurchaseReturn;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\TaxCalculator;
use Modules\Accounting\Application\TaxQuery;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Company\Application\CompanyAccess;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Product\Application\Variant\GetVariantReturnProfile;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Enums\PurchaseReturnStatus;
use Modules\Purchasing\Models\PurchaseInvoice;
use Modules\Purchasing\Models\PurchaseInvoiceItem;
use Modules\Purchasing\Models\PurchaseReturn;
use Modules\Warehouse\Application\StockLayer\GetLayerLineage;
use Modules\Warehouse\Application\StockReservation\GetAvailableStock;
use Modules\Warehouse\Application\StockTransfer\GetStockTransferDetail;
use Modules\Warehouse\Application\Warehouse\GetWarehouse;

class CreatePurchaseReturn
{
    public function __construct(
        private readonly GetPurchaseVariants $purchaseVariants,
        private readonly GetVariantReturnProfile $returnProfiles,
        private readonly ResolveReturnVariant $returnVariants,
        private readonly GetStockTransferDetail $returnTransfers,
        private readonly GetWarehouse $warehouses,
        private readonly GetAvailableStock $availableStock,
        private readonly GetLayerLineage $layerLineage,
        private readonly TaxQuery $taxQuery,
        private readonly TaxCalculator $taxCalculator,
        private readonly ApprovalEngine $approvalEngine,
        private readonly FinalizePurchaseReturn $finalizePurchaseReturn,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $branchIds  Branch scope. Null = cabang retur saja.
     */
    public function execute(array $data, string $branchCode, ?int $userId = null, ?string $userName = null, ?array $branchIds = null): PurchaseReturn
    {
        $variants = collect($this->purchaseVariants->execute($branchIds))->keyBy('id');
        $taxes = collect($this->taxQuery->listForPurchase())->keyBy('id');
        $creatorId = $userId ?? (int) auth()->id();
        $creatorName = $userName ?? auth()->user()?->name;

        return DB::transaction(function () use ($data, $variants, $taxes, $creatorId, $creatorName, $branchIds) {
            $hqBranchId = CompanyAccess::headquartersBranchId();
            if ($hqBranchId !== null && (int) $data['branch_id'] !== $hqBranchId) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Retur pembelian hanya dapat dibuat untuk Head Office.',
                ]);
            }

            $invoice = PurchaseInvoice::whereKey((int) $data['purchase_invoice_id'])
                ->lockForUpdate()
                ->first();

            if (! $invoice) {
                throw ValidationException::withMessages([
                    'purchase_invoice_id' => 'Faktur pembelian tidak ditemukan.',
                ]);
            }

            if (! in_array($invoice->status, [PurchaseInvoiceStatus::Approved, PurchaseInvoiceStatus::PartiallyPaid], true)) {
                throw ValidationException::withMessages([
                    'purchase_invoice_id' => 'Hanya faktur disetujui/disicil yang dapat diretur.',
                ]);
            }

            if ((int) $data['supplier_id'] !== (int) $invoice->supplier_id) {
                throw ValidationException::withMessages([
                    'supplier_id' => 'Supplier retur tidak sama dengan supplier faktur.',
                ]);
            }

            $warehouse = $this->warehouses->execute(
                (int) $data['warehouse_id'],
                $branchIds ?? [(int) $data['branch_id']]
            );

            if (! $warehouse || ! $warehouse->is_active) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Gudang tidak ditemukan atau tidak aktif.',
                ]);
            }

            if ($hqBranchId !== null && (int) $warehouse->branch_id !== $hqBranchId) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Gudang retur harus milik Head Office.',
                ]);
            }

            $returnDate = $this->parseReturnDate($data['return_date'] ?? null);
            $transferId = $this->resolveTransfer(
                $data['return_transfer_id'] ?? null,
                (int) $warehouse->id,
                $branchIds ?? [(int) $data['branch_id']]
            );

            $invoiceItems = PurchaseInvoiceItem::where('purchase_invoice_id', $invoice->id)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lines = $this->validateLines(
                $data['items'] ?? [],
                $invoiceItems,
                $variants,
                $taxes,
                (bool) $invoice->is_tax_inclusive,
                (int) $warehouse->id,
                (string) ($warehouse->name ?? ''),
                (int) ($warehouse->branch_id ?? 0),
                $transferId,
            );

            [$lines, $subtotal, $taxAmount] = $this->computeTotals($lines, (bool) $invoice->is_tax_inclusive);
            $total = $subtotal + $taxAmount;

            $this->validateTags($data['tag_ids'] ?? []);

            if ($transferId !== null) {
                $this->validateTransferMatchesInvoicePurchaseOrder($invoice, $lines, (int) $warehouse->id, $transferId);
            }

            $number = $this->nextNumber($invoice);

            $purchaseReturn = PurchaseReturn::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_invoice_id' => $invoice->id,
                'warehouse_id' => $warehouse->id,
                'return_transfer_id' => $transferId,
                'status' => PurchaseReturnStatus::Pending->value,
                'return_date' => $returnDate,
                'message' => $data['message'] ?? null,
                'memo' => $data['memo'] ?? null,
                'currency_code' => 'IDR',
                'is_tax_inclusive' => (bool) $invoice->is_tax_inclusive,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            foreach ($lines as $line) {
                $purchaseReturn->items()->create([
                    'purchase_invoice_item_id' => $line['purchase_invoice_item_id'],
                    'product_variant_id' => $line['product_variant_id'],
                    'stock_variant_id' => $line['stock_variant_id'],
                    'product_name' => $line['product_name'],
                    'sku' => $line['sku'],
                    'uom_name' => $line['uom_name'],
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'tax_id' => $line['tax_id'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_breakdown' => $line['tax_breakdown'],
                    'line_total' => $line['line_total'],
                ]);
            }

            if (! empty($data['tag_ids'])) {
                $purchaseReturn->tags()->attach(array_values(array_unique(array_map('intval', $data['tag_ids']))));
            }

            $mapping = $this->approvalEngine->evaluateAndMap([
                'transaction_type' => 'purchase_return',
                'transaction_id' => $purchaseReturn->id,
                'document_number' => $purchaseReturn->number,
                'created_by' => $creatorId,
                'created_by_name' => $creatorName,
                'branch_id' => $purchaseReturn->branch_id,
                'total' => $total,
                'currency_code' => 'IDR',
            ]);

            if (! $mapping) {
                $this->finalizePurchaseReturn->execute($purchaseReturn->id);
                $purchaseReturn->refresh();
            }

            return $purchaseReturn->load('items');
        });
    }

    /**
     * Provenance lunak: bila faktur punya PO dan layer transfer punya root
     * PO yang terbukti berbeda, tolak retur. Bila root tak diketahui (data
     * lama) atau faktur tanpa PO, lewati — jangan blokir data lama.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function validateTransferMatchesInvoicePurchaseOrder(
        PurchaseInvoice $invoice,
        array $lines,
        int $warehouseId,
        int $transferId
    ): void {
        $invoicePoId = $invoice->purchase_order_id !== null
            ? (int) $invoice->purchase_order_id
            : null;

        if ($invoicePoId === null) {
            return;
        }

        $stockVariantIds = array_values(array_unique(array_filter(array_map(
            fn (array $line): ?int => isset($line['stock_variant_id']) && $line['stock_variant_id'] !== null
                ? (int) $line['stock_variant_id']
                : null,
            $lines
        ))));

        if ($stockVariantIds === []) {
            return;
        }

        $transferPoId = $this->layerLineage->rootPurchaseOrderIdForTransfer(
            $warehouseId,
            $transferId,
            $stockVariantIds
        );

        if ($transferPoId !== null && $transferPoId !== $invoicePoId) {
            throw ValidationException::withMessages([
                'return_transfer_id' => 'Transfer retur berasal dari PO berbeda dengan PO faktur sumber.',
            ]);
        }
    }

    /**
     * Transfer retur yang di-link (opsional). Memastikan transfer ada dalam
     * scope cabang peminta, menuju gudang retur, dan sudah diterima HO;
     * ketersediaan stoknya ditegakkan per baris via layer transfer itu.
     *
     * @param  list<int>  $branchIds
     */
    private function resolveTransfer(mixed $value, int $warehouseId, array $branchIds): ?int
    {
        if (empty($value)) {
            return null;
        }

        try {
            $transfer = $this->returnTransfers->forScope((int) $value, $branchIds);
        } catch (ModelNotFoundException $e) {
            throw ValidationException::withMessages([
                'return_transfer_id' => 'Transfer retur tidak ditemukan.',
            ]);
        }

        if ((int) $transfer->to_warehouse_id !== $warehouseId) {
            throw ValidationException::withMessages([
                'return_transfer_id' => 'Transfer retur tidak menuju gudang ini.',
            ]);
        }

        if ((string) $transfer->status !== 'received') {
            throw ValidationException::withMessages([
                'return_transfer_id' => 'Transfer retur belum diterima HO sehingga belum bisa diretur.',
            ]);
        }

        return (int) $transfer->id;
    }

    private function parseReturnDate(mixed $value): string
    {
        if (empty($value)) {
            throw ValidationException::withMessages([
                'return_date' => 'Tanggal retur wajib diisi.',
            ]);
        }

        try {
            $date = new \DateTimeImmutable((string) $value);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'return_date' => 'Tanggal retur tidak valid.',
            ]);
        }

        if ($date->format('Y-m-d') > today()->toDateString()) {
            throw ValidationException::withMessages([
                'return_date' => 'Tanggal retur tidak boleh melebihi hari ini.',
            ]);
        }

        return $date->format('Y-m-d');
    }

    /**
     * Harga dikunci = harga faktur (harga DO). Stok terpantau wajib bulat
     * dan wajib tersedia di gudang pilihan (fail fast; finalize tetap
     * menjadi penjaga atomik bila stok berpindah saat menunggu approval).
     *
     * @return list<array<string, mixed>>
     */
    private function validateLines(mixed $items, mixed $invoiceItems, mixed $variants, mixed $taxes, bool $isTaxInclusive, int $warehouseId, string $warehouseName, int $warehouseBranchId, ?int $transferId): array
    {
        if (! is_array($items) || $items === []) {
            throw ValidationException::withMessages([
                'items' => 'Retur minimal terdiri dari 1 baris.',
            ]);
        }

        $lines = [];

        foreach (array_values($items) as $index => $item) {
            $invoiceItemId = (int) ($item['purchase_invoice_item_id'] ?? 0);
            $qty = (float) ($item['qty'] ?? 0);

            $invoiceItem = $invoiceItems->get($invoiceItemId);

            if (! $invoiceItem) {
                throw ValidationException::withMessages([
                    "items.{$index}.purchase_invoice_item_id" => 'Baris faktur tidak ditemukan.',
                ]);
            }

            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    "items.{$index}.qty" => 'Qty retur harus lebih dari 0.',
                ]);
            }

            $remaining = (float) $invoiceItem->qty - (float) ($invoiceItem->qty_returned ?? 0);

            if ($qty - $remaining > 0.0001) {
                throw ValidationException::withMessages([
                    "items.{$index}.qty" => 'Qty retur melebihi sisa yang bisa diretur ('.rtrim(rtrim(number_format($remaining, 4), '0'), '.').').',
                ]);
            }

            $variant = $variants->get((int) $invoiceItem->product_variant_id);

            if (! $variant) {
                throw ValidationException::withMessages([
                    "items.{$index}.purchase_invoice_item_id" => 'Varian produk sudah tidak aktif.',
                ]);
            }

            $profile = $this->returnProfiles->execute((int) $invoiceItem->product_variant_id);
            $stockVariantId = $this->returnVariants->execute(
                (int) $invoiceItem->product_variant_id,
                $warehouseBranchId,
                $transferId
            );

            if ($profile && $profile['is_tracked']) {
                if (abs($qty - round($qty)) > 0.0001) {
                    throw ValidationException::withMessages([
                        "items.{$index}.qty" => 'Qty produk terpantau stok harus bilangan bulat.',
                    ]);
                }

                if ($stockVariantId === null) {
                    throw ValidationException::withMessages([
                        "items.{$index}.qty" => "Varian tidak tersedia di gudang {$warehouseName} (hasil receive transfer tak ditemukan).",
                    ]);
                }

                $available = $transferId !== null
                    ? $this->availableStock->forItemsFromTransfer($warehouseId, [$stockVariantId], $transferId)[$stockVariantId] ?? 0
                    : $this->availableStock->forItem($warehouseId, (int) $invoiceItem->product_variant_id);

                if ($qty - $available > 0.0001) {
                    $scope = $transferId !== null ? " dari transfer #{$transferId}" : '';
                    throw ValidationException::withMessages([
                        "items.{$index}.qty" => "Stok tidak mencukupi di gudang {$warehouseName}{$scope} (tersedia: {$available}, diminta: ".rtrim(rtrim(number_format($qty, 4), '0'), '.').').',
                    ]);
                }
            }

            $taxId = $invoiceItem->tax_id !== null ? (int) $invoiceItem->tax_id : null;
            $taxDef = $taxId ? $taxes->get($taxId) : null;

            $lines[] = [
                'purchase_invoice_item_id' => $invoiceItem->id,
                'product_variant_id' => (int) $invoiceItem->product_variant_id,
                'stock_variant_id' => $profile && $profile['is_tracked'] ? $stockVariantId : null,
                'product_name' => $variant['product_name'] ?? '',
                'sku' => $variant['sku'] ?? '',
                'uom_name' => $variant['uom_name'] ?? null,
                'qty' => $qty,
                'unit_price' => (float) $invoiceItem->unit_price,
                'tax_id' => $taxId,
                'tax_rate' => $taxDef ? (float) ($taxDef['rate'] ?? 0) : (float) $invoiceItem->tax_rate,
                'tax_def' => $taxDef ?? ($taxId ? ['id' => $taxId, 'rate' => (float) $invoiceItem->tax_rate] : null),
                'tax_breakdown' => null,
                'line_total' => 0.0,
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
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

    private function validateTags(mixed $tagIds): void
    {
        $ids = array_values(array_unique(array_map('intval', (array) ($tagIds ?? []))));

        if ($ids === []) {
            return;
        }

        $found = DB::table('purchase_tags')->whereIn('id', $ids)->pluck('id')->all();

        if (count($found) !== count($ids)) {
            throw ValidationException::withMessages([
                'tag_ids' => 'Tag tidak ditemukan.',
            ]);
        }
    }

    /**
     * Nomor RBL-{sanitasi no faktur}-{xx}: sanitasi buang semua
     * non-alfanumerik agar aman di URL; xx = urutan retur per faktur.
     */
    private function nextNumber(PurchaseInvoice $invoice): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9]/', '', (string) $invoice->number) ?: 'INV'.$invoice->id;

        $sequence = PurchaseReturn::where('purchase_invoice_id', $invoice->id)
            ->lockForUpdate()
            ->pluck('id')
            ->count() + 1;

        return sprintf('RBL-%s-%02d', $sanitized, $sequence);
    }
}
