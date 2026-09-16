<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\TaxCalculator;
use Modules\Accounting\Application\TaxQuery;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Models\PurchaseOrder;

class CreatePurchaseOrder
{
    public function __construct(
        private readonly GetPurchaseVariants $purchaseVariants,
        private readonly TaxQuery $taxQuery,
        private readonly TaxCalculator $taxCalculator,
        private readonly ApprovalEngine $approvalEngine,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $branchIds  Branch scope for variant resolution. Null = all.
     */
    public function execute(array $data, string $branchCode, ?int $userId = null, ?string $userName = null, ?array $branchIds = null): PurchaseOrder
    {
        $variants = collect($this->purchaseVariants->execute($branchIds))->keyBy('id');
        $taxes = collect($this->taxQuery->listForPurchase())->keyBy('id');
        $creatorId = $userId ?? (int) auth()->id();
        $creatorName = $userName ?? auth()->user()?->name;

        return DB::transaction(function () use ($data, $branchCode, $variants, $taxes, $creatorId, $creatorName) {
            $orderDate = $data['order_date'] ?? date('Y-m-d');
            $dateObj = Carbon::parse($orderDate);
            $year = $dateObj->format('Y');
            $month = $dateObj->format('m');
            $day = $dateObj->format('d');

            // Lock branch POs to prevent duplicate numbers under concurrency
            // Note: lockForUpdate()->count() is not allowed on Postgres (FOR UPDATE with aggregate),
            // so we lock the rows via SELECT ... FOR UPDATE without aggregation.
            $lockedIds = PurchaseOrder::where('branch_id', $data['branch_id'])->lockForUpdate()->pluck('id');
            $sequence = $lockedIds->count() + 1;
            $number = sprintf('PO/%s/%s/%s/%s/%03d', $branchCode, $year, $month, $day, $sequence);

            $isTaxInclusive = (bool) ($data['is_tax_inclusive'] ?? false);
            $subtotal = 0.0;
            $taxAmount = 0.0;

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty_ordered'];
                $unitPrice = (float) $item['unit_price'];
                $taxId = $item['tax_id'] ?? null;
                $taxDef = $taxId ? $taxes->get($taxId) : null;
                $line = $this->taxLine($qty * $unitPrice, $taxDef, $isTaxInclusive);

                $subtotal += $line['subtotal'];
                $taxAmount += $line['tax'];
            }

            $total = $subtotal + $taxAmount;

            // Sort items by destination_expected_date ASC so paling atas = paling cepat kirim
            $sortedItems = collect($data['items'])->sortBy(fn ($it) => $it['destination_expected_date'] ?? '9999-12-31')->values()->all();
            $data['items'] = $sortedItems;

            $destBranchIds = collect($data['items'])->pluck('destination_branch_id')->unique()->values();
            $branchMode = $destBranchIds->count() > 1 ? 'multi' : 'single';

            $po = PurchaseOrder::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'supplier_email' => $data['supplier_email'] ?? null,
                'supplier_reference' => $data['supplier_reference'] ?? null,
                'billing_address' => $data['billing_address'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'branch_mode' => $branchMode,
                'status' => PurchaseOrderStatus::Pending,
                'payment_term' => $data['payment_term'] ?? null,
                'order_date' => $orderDate,
                'due_date' => $data['due_date'] ?? null,
                'expected_date' => $data['expected_date'] ?? null,
                'note' => $data['note'] ?? null,
                'currency_code' => 'IDR',
                'is_tax_inclusive' => $isTaxInclusive,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            foreach ($data['items'] as $item) {
                $variant = $variants->get($item['product_variant_id']);
                $qty = (float) $item['qty_ordered'];
                $unitPrice = (float) $item['unit_price'];
                $taxId = $item['tax_id'] ?? null;
                $taxDef = $taxId ? $taxes->get($taxId) : null;
                $line = $this->taxLine($qty * $unitPrice, $taxDef, $isTaxInclusive);

                $po->items()->create([
                    'destination_branch_id' => $item['destination_branch_id'],
                    'destination_warehouse_id' => $item['destination_warehouse_id'],
                    'destination_expected_date' => $item['destination_expected_date'] ?? null,
                    'product_variant_id' => $item['product_variant_id'],
                    'product_name' => $variant['product_name'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'uom_name' => $variant['uom_name'] ?? null,
                    'description' => $item['description'] ?? null,
                    'qty_ordered' => $qty,
                    'qty_received' => 0,
                    'unit_price' => $unitPrice,
                    'tax_id' => $taxId,
                    'tax_rate' => $line['rate'],
                    'tax_breakdown' => $line['breakdown'],
                    'line_total' => $line['total'],
                ]);
            }

            if (! empty($data['tag_ids'])) {
                $po->tags()->sync($data['tag_ids']);
            }

            $mapping = $this->approvalEngine->evaluateAndMap([
                'transaction_type' => 'purchase_order',
                'transaction_id' => $po->id,
                'document_number' => $po->number,
                'created_by' => $creatorId,
                'created_by_name' => $creatorName,
                'branch_id' => $po->branch_id,
                'total' => $total,
                'currency_code' => 'IDR',
            ]);

            $finalStatus = $mapping ? PurchaseOrderStatus::Pending : PurchaseOrderStatus::Approved;
            $po->update(['status' => $finalStatus]);

            return $po->load('items');
        });
    }

    /**
     * Pajak per baris via TaxCalculator; diskon tidak ada di purchasing.
     *
     * @param  array<string, mixed>|null  $taxDef  Proyeksi TaxQuery.
     * @return array{subtotal: float, tax: float, total: float, rate: float, breakdown: list<array{tax_id: int, rate: float, amount: float}>|null}
     */
    private function taxLine(float $gross, ?array $taxDef, bool $isTaxInclusive): array
    {
        $result = $this->taxCalculator->calculate($gross, $taxDef, $isTaxInclusive);
        $rate = $taxDef ? (float) ($taxDef['rate'] ?? 0) : 0.0;

        if ($isTaxInclusive) {
            return [
                'subtotal' => $gross - $result['total'],
                'tax' => $result['total'],
                'total' => $gross,
                'rate' => $rate,
                'breakdown' => $result['breakdown'] === [] ? null : $result['breakdown'],
            ];
        }

        return [
            'subtotal' => $gross,
            'tax' => $result['total'],
            'total' => $gross + $result['total'],
            'rate' => $rate,
            'breakdown' => $result['breakdown'] === [] ? null : $result['breakdown'],
        ];
    }
}
