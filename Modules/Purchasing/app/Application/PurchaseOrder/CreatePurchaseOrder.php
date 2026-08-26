<?php

namespace Modules\Purchasing\Application\PurchaseOrder;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\GetPurchaseTaxes;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Models\PurchaseOrder;

class CreatePurchaseOrder
{
    public function __construct(
        private readonly GetPurchaseVariants $purchaseVariants,
        private readonly GetPurchaseTaxes $getPurchaseTaxes,
        private readonly ApprovalEngine $approvalEngine,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, string $branchCode, ?int $userId = null, ?string $userName = null): PurchaseOrder
    {
        $variants = collect($this->purchaseVariants->execute())->keyBy('id');
        $taxes = collect($this->getPurchaseTaxes->execute())->keyBy('id');
        $creatorId = $userId ?? (int) auth()->id();
        $creatorName = $userName ?? auth()->user()?->name;

        return DB::transaction(function () use ($data, $branchCode, $variants, $taxes, $creatorId, $creatorName) {
            $orderDate = $data['order_date'] ?? date('Y-m-d');
            $dateObj = Carbon::parse($orderDate);
            $year = $dateObj->format('Y');
            $month = $dateObj->format('m');
            $day = $dateObj->format('d');

            $sequence = PurchaseOrder::where('branch_id', $data['branch_id'])->count() + 1;
            $number = sprintf('PO/%s/%s/%s/%s/%03d', $branchCode, $year, $month, $day, $sequence);

            $isTaxInclusive = (bool) ($data['is_tax_inclusive'] ?? false);
            $subtotal = 0.0;
            $taxAmount = 0.0;

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty_ordered'];
                $unitPrice = (float) $item['unit_price'];
                $taxId = $item['tax_id'] ?? null;
                $taxRate = $taxId ? (float) ($taxes->get($taxId)['rate'] ?? 0) : 0.0;

                if ($isTaxInclusive && $taxRate > 0) {
                    $lineTotal = $qty * $unitPrice;
                    $lineSubtotal = $lineTotal / (1 + ($taxRate / 100));
                    $lineTax = $lineTotal - $lineSubtotal;
                } else {
                    $lineSubtotal = $qty * $unitPrice;
                    $lineTax = $lineSubtotal * ($taxRate / 100);
                }

                $subtotal += $lineSubtotal;
                $taxAmount += $lineTax;
            }

            $total = $subtotal + $taxAmount;

            $po = PurchaseOrder::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'source_request_id' => $data['source_request_id'] ?? null,
                'source_quote_id' => $data['source_quote_id'] ?? null,
                'status' => 'pending',
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
                $taxRate = $taxId ? (float) ($taxes->get($taxId)['rate'] ?? 0) : 0.0;
                $lineTotal = ($qty * $unitPrice) * (1 + ($taxRate / 100));

                $po->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'product_name' => $variant['product_name'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'uom_name' => $variant['uom_name'] ?? null,
                    'qty_ordered' => $qty,
                    'qty_received' => 0,
                    'unit_price' => $unitPrice,
                    'tax_id' => $taxId,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotal,
                ]);
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

            $finalStatus = $mapping ? 'pending' : 'approved';
            $po->update(['status' => $finalStatus]);

            return $po->load('items');
        });
    }
}
