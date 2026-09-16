<?php

namespace Modules\Sales\Application\SalesInvoice;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\TaxQuery;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Contact\Application\GetContacts;
use Modules\Product\Application\Variant\GetSaleVariants;
use Modules\Sales\Domain\Rules\CalculateInvoiceTotals;
use Modules\Sales\Enums\SalesInvoiceStatus;
use Modules\Sales\Models\SalesInvoice;
use Modules\Warehouse\Application\StockReservation\GetAvailableStock;
use Modules\Warehouse\Application\StockReservation\ReserveStock;
use Modules\Warehouse\Application\Warehouse\GetWarehouses;

class CreateSalesInvoice
{
    public function __construct(
        private readonly GetSaleVariants $saleVariants,
        private readonly TaxQuery $taxQuery,
        private readonly GetContacts $contacts,
        private readonly GetWarehouses $warehouses,
        private readonly GetAvailableStock $availableStock,
        private readonly ReserveStock $reserveStock,
        private readonly ApprovalEngine $approvalEngine,
        private readonly CalculateInvoiceTotals $totals,
        private readonly FinalizeApprovedSalesInvoice $finalizeApproved,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $branchIds  Scope for master resolution. Null = all.
     */
    public function execute(
        array $data,
        int $branchId,
        string $branchCode,
        ?int $userId = null,
        ?string $userName = null,
        ?array $branchIds = null,
    ): SalesInvoice {
        $variants = collect($this->saleVariants->execute($branchIds))->keyBy('id');
        $taxes = collect($this->taxQuery->listForSale())->keyBy('id');
        $customers = collect($this->contacts->execute('customer', $branchIds ?? []))->keyBy('id');
        $employees = collect($this->contacts->execute('employee', $branchIds ?? []))->keyBy('id');
        $creatorId = $userId ?? (int) auth()->id();
        $creatorName = $userName ?? auth()->user()?->name;

        return DB::transaction(function () use (
            $data, $branchId, $branchCode, $variants, $taxes, $customers, $employees, $creatorId, $creatorName
        ) {
            $customer = $customers->get((int) $data['customer_id']);
            $salesperson = $employees->get((int) $data['salesperson_id']);

            if (! $customer) {
                throw ValidationException::withMessages([
                    'customer_id' => 'Pelanggan tidak ditemukan dalam cakupan cabang Anda.',
                ]);
            }

            if (! $salesperson) {
                throw ValidationException::withMessages([
                    'salesperson_id' => 'Sales/Marketing tidak ditemukan dalam cakupan cabang Anda.',
                ]);
            }

            $warehouse = $this->resolveWarehouse($data, $branchId);
            $isTaxInclusive = (bool) ($data['is_tax_inclusive'] ?? false);

            $lines = $this->validateLines($data['items'], $branchId, $variants, $taxes);

            $computed = $this->totals->execute($lines, [
                'type' => $data['invoice_discount_type'] ?? null,
                'value' => $data['invoice_discount_value'] ?? null,
            ], $isTaxInclusive);

            $this->validateInvoiceDiscount($data, $computed['net_after_line_discount']);
            $this->validateAvailability($computed['lines'], $lines, (int) $warehouse['id']);

            $number = $this->nextNumber($branchId, $branchCode, (string) $data['invoice_date']);

            $inv = SalesInvoice::create([
                'number' => $number,
                'branch_id' => $branchId,
                'customer_id' => $customer['id'],
                'customer_name' => $customer['name'],
                'customer_email' => $data['customer_email'] ?? $customer['email'] ?? null,
                'transaction_type' => $data['transaction_type'],
                'warehouse_id' => $warehouse['id'],
                'warehouse_code' => $warehouse['code'],
                'warehouse_name' => $warehouse['name'],
                'salesperson_id' => $salesperson['id'],
                'salesperson_name' => $salesperson['name'],
                'payment_term' => $data['payment_term'] ?? null,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'status' => SalesInvoiceStatus::Pending,
                'currency_code' => 'IDR',
                'is_tax_inclusive' => $isTaxInclusive,
                'subtotal' => $computed['subtotal'],
                'line_discount_total' => $computed['line_discount_total'],
                'invoice_discount_type' => $data['invoice_discount_type'] ?? null,
                'invoice_discount_value' => $data['invoice_discount_value'] ?? 0,
                'invoice_discount_amount' => $computed['invoice_discount_amount'],
                'tax_amount' => $computed['tax_amount'],
                'total' => $computed['total'],
            ]);

            foreach ($lines as $i => $line) {
                $variant = $variants->get($line['product_variant_id']);
                $calc = $computed['lines'][$i];

                $inv->items()->create([
                    'product_variant_id' => $line['product_variant_id'],
                    'product_name' => $variant['product_name'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'uom_name' => $variant['uom_name'] ?? null,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount_type' => $line['discount_type'],
                    'discount_value' => $line['discount_value'] ?? 0,
                    'discount_amount' => $calc['discount_amount'],
                    'line_gross' => $calc['line_gross'],
                    'line_net' => $calc['line_net'],
                    'tax_id' => $line['tax_id'],
                    'tax_rate' => $line['tax_rate'],
                    'tax_amount' => $calc['tax_amount'],
                    'tax_breakdown' => $calc['tax_breakdown'] === [] ? null : $calc['tax_breakdown'],
                    'line_total' => $calc['line_total'],
                ]);
            }

            $this->reserveStock->execute(
                $inv->id,
                (int) $warehouse['id'],
                collect($lines)->map(fn (array $line): array => [
                    'product_variant_id' => $line['product_variant_id'],
                    'qty' => $line['qty'],
                ])->all()
            );

            $mapping = $this->approvalEngine->evaluateAndMap([
                'transaction_type' => 'sales_invoice',
                'transaction_id' => $inv->id,
                'document_number' => $inv->number,
                'created_by' => $creatorId,
                'created_by_name' => $creatorName,
                'branch_id' => $inv->branch_id,
                'total' => $computed['total'],
                'currency_code' => 'IDR',
            ]);

            if (! $mapping) {
                $this->finalizeApproved->execute($inv->fresh('items'));
            }

            return $inv->load('items');
        });
    }

    /**
     * Gudang harus milik cabang transaksi. Tipe reguler boleh memakai
     * gudang regular atau ritel; konsinyasi hanya gudang konsinyasi.
     *
     * @param  array<string, mixed>  $data
     * @return array{id: int, code: string, name: string}
     */
    private function resolveWarehouse(array $data, int $branchId): array
    {
        $allowedTypes = $data['transaction_type'] === 'consignment'
            ? ['consignment']
            : ['regular', 'retail'];

        $warehouse = collect($this->warehouses->optionsForSale($branchId))
            ->first(
                fn (array $option): bool => (int) $option['id'] === (int) $data['warehouse_id']
                    && in_array($option['warehouse_type'], $allowedTypes, true)
            );

        if (! $warehouse) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang tidak valid untuk tipe transaksi dan cabang ini.',
            ]);
        }

        return $warehouse;
    }

    /**
     * Validasi + normalisasi baris: variant milik cabang, batas diskon,
     * pajak eligible untuk penjualan.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{product_variant_id: int, qty: int, unit_price: float, discount_type: string|null, discount_value: float, tax_id: int|null, tax_rate: float, tax: array<string, mixed>|null}>
     */
    private function validateLines(array $items, int $branchId, mixed $variants, mixed $taxes): array
    {
        $lines = [];

        foreach ($items as $index => $item) {
            $variant = $variants->get((int) $item['product_variant_id']);

            if (! $variant) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'Produk tidak ditemukan dalam cakupan cabang Anda.',
                ]);
            }

            if ((int) $variant['branch_id'] !== $branchId) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_variant_id" => 'Produk bukan milik cabang transaksi ini.',
                ]);
            }

            $qty = (int) $item['qty'];
            $unitPrice = (float) $item['unit_price'];
            $discountType = $item['discount_type'] ?? null;
            $discountValue = (float) ($item['discount_value'] ?? 0);

            $this->validateLineDiscount($index, $discountType, $discountValue, $qty * $unitPrice);

            $taxId = $item['tax_id'] ?? null;
            $taxRate = 0.0;
            $taxDef = null;

            if ($taxId !== null && $taxId !== '') {
                $tax = $taxes->get((int) $taxId);

                if (! $tax) {
                    throw ValidationException::withMessages([
                        "items.{$index}.tax_id" => 'Pajak tidak valid untuk penjualan.',
                    ]);
                }

                $taxRate = (float) $tax['rate'];
                $taxDef = $tax;
            }

            $lines[] = [
                'product_variant_id' => (int) $item['product_variant_id'],
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'discount_type' => $discountType ?: null,
                'discount_value' => $discountValue,
                'tax_id' => $taxId ? (int) $taxId : null,
                'tax_rate' => $taxRate,
                'tax' => $taxDef,
            ];
        }

        return $lines;
    }

    private function validateLineDiscount(int $index, ?string $type, float $value, float $gross): void
    {
        if ($type === null || $type === '') {
            return;
        }

        if ($type === 'percent' && ($value < 0 || $value > 100)) {
            throw ValidationException::withMessages([
                "items.{$index}.discount_value" => 'Diskon persen harus antara 0 sampai 100.',
            ]);
        }

        if ($type === 'nominal' && $value > $gross) {
            throw ValidationException::withMessages([
                "items.{$index}.discount_value" => 'Diskon nominal tidak boleh melebihi jumlah baris.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateInvoiceDiscount(array $data, float $netAfterLineDiscount): void
    {
        $type = $data['invoice_discount_type'] ?? null;
        $value = (float) ($data['invoice_discount_value'] ?? 0);

        if ($type === null || $type === '') {
            return;
        }

        if ($type === 'percent' && ($value < 0 || $value > 100)) {
            throw ValidationException::withMessages([
                'invoice_discount_value' => 'Diskon invoice persen harus antara 0 sampai 100.',
            ]);
        }

        if ($type === 'nominal' && $value > $netAfterLineDiscount) {
            throw ValidationException::withMessages([
                'invoice_discount_value' => 'Diskon invoice tidak boleh melebihi total setelah diskon per baris.',
            ]);
        }
    }

    /**
     * Pre-check ketersediaan per baris (UX: error per index) sebelum
     * reserve atomik. Race antar transaksi tetap diamankan
     * lockForUpdate di ReserveStock.
     *
     * @param  list<array{line_gross: float}>  $computedLines
     * @param  list<array{product_variant_id: int, qty: int}>  $lines
     */
    private function validateAvailability(array $computedLines, array $lines, int $warehouseId): void
    {
        $variantIds = array_values(array_unique(array_column($lines, 'product_variant_id')));

        $available = $this->availableStock->forItems($warehouseId, $variantIds);

        foreach ($lines as $index => $line) {
            $variantId = $line['product_variant_id'];

            if (($available[$variantId] ?? 0) < $line['qty']) {
                throw ValidationException::withMessages([
                    "items.{$index}.qty" => sprintf(
                        'Stok tidak cukup di gudang ini. Tersedia: %d, diminta: %d.',
                        $available[$variantId] ?? 0,
                        $line['qty']
                    ),
                ]);
            }
        }
    }

    /**
     * Nomor SI/{cabang}/YYYYMMDD/XXX. XXX urut per cabang per tanggal
     * faktur; dikunci seperti PO anti duplikat.
     */
    private function nextNumber(int $branchId, string $branchCode, string $invoiceDate): string
    {
        $dateObj = Carbon::parse($invoiceDate);
        $datePart = $dateObj->format('Ymd');

        $lockedIds = SalesInvoice::where('branch_id', $branchId)
            ->whereDate('invoice_date', $dateObj->toDateString())
            ->lockForUpdate()
            ->pluck('id');

        $sequence = $lockedIds->count() + 1;

        return sprintf('SI/%s/%s/%03d', $branchCode, $datePart, $sequence);
    }
}
