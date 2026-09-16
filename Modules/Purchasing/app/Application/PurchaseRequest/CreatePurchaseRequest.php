<?php

namespace Modules\Purchasing\Application\PurchaseRequest;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\TaxCalculator;
use Modules\Accounting\Application\TaxQuery;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Enums\PurchaseRequestStatus;
use Modules\Purchasing\Models\PurchaseRequest;

class CreatePurchaseRequest
{
    public function __construct(
        private readonly GetPurchaseVariants $purchaseVariants,
        private readonly TaxQuery $taxQuery,
        private readonly TaxCalculator $taxCalculator,
        private readonly ApprovalEngine $approvalEngine,
    ) {}

    /**
     * Create a new purchase request with snapshot product data.
     *
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $branchIds  Branch scope for variant resolution. Null = all.
     */
    public function execute(array $data, string $branchCode, ?int $userId = null, ?string $userName = null, ?array $branchIds = null): PurchaseRequest
    {
        $variants = collect($this->purchaseVariants->execute($branchIds))->keyBy('id');
        $creatorId = $userId ?? (int) auth()->id();
        $creatorName = $userName ?? auth()->user()?->name;

        return DB::transaction(function () use ($data, $branchCode, $variants, $creatorId, $creatorName) {
            $sequence = PurchaseRequest::where('branch_id', $data['branch_id'])->count() + 1;
            $number = 'PR-'.$branchCode.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            $taxes = collect($this->taxQuery->listForPurchase())->keyBy('id');
            $subtotal = 0.0;
            $taxAmount = 0.0;
            $computed = [];

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty_requested'];
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $lineSubtotal = $qty * $unitPrice;
                $subtotal += $lineSubtotal;

                $taxId = $item['tax_id'] ?? null;
                $taxDef = $taxId ? $taxes->get($taxId) : null;
                $taxResult = $this->taxCalculator->calculate($lineSubtotal, $taxDef);
                $taxAmount += $taxResult['total'];

                $computed[] = [
                    'tax_rate' => $taxDef ? (float) ($taxDef['rate'] ?? 0) : null,
                    'tax_breakdown' => $taxResult['breakdown'] === [] ? null : $taxResult['breakdown'],
                ];
            }

            $total = $subtotal + $taxAmount;

            $request = PurchaseRequest::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'status' => PurchaseRequestStatus::Pending,
                'request_date' => $data['request_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'note' => $data['note'] ?? null,
                'currency_code' => 'IDR',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            foreach ($data['items'] as $i => $item) {
                $variant = $variants->get($item['product_variant_id']);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $lineTotal = (float) $item['qty_requested'] * $unitPrice;

                $request->items()->create([
                    'product_variant_id' => $item['product_variant_id'],
                    'product_name' => $variant['product_name'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'uom_name' => $variant['uom_name'] ?? null,
                    'qty_requested' => $item['qty_requested'],
                    'unit_price' => $item['unit_price'] ?? null,
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_rate' => ($item['tax_id'] ?? null) !== null ? $computed[$i]['tax_rate'] : null,
                    'tax_breakdown' => ($item['tax_id'] ?? null) !== null ? $computed[$i]['tax_breakdown'] : null,
                    'line_total' => $lineTotal,
                ]);
            }

            $mapping = $this->approvalEngine->evaluateAndMap([
                'transaction_type' => 'purchase_request',
                'transaction_id' => $request->id,
                'document_number' => $request->number,
                'created_by' => $creatorId,
                'created_by_name' => $creatorName,
                'branch_id' => $request->branch_id,
                'total' => $total,
                'currency_code' => 'IDR',
            ]);

            $finalStatus = $mapping ? PurchaseRequestStatus::Pending : PurchaseRequestStatus::Approved;
            $request->update(['status' => $finalStatus]);

            return $request->load('items');
        });
    }
}
