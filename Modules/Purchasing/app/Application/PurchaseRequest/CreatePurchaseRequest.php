<?php

namespace Modules\Purchasing\Application\PurchaseRequest;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\GetPurchaseTaxes;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Models\PurchaseRequest;

class CreatePurchaseRequest
{
    public function __construct(
        private readonly GetPurchaseVariants $purchaseVariants,
        private readonly GetPurchaseTaxes $getPurchaseTaxes,
        private readonly ApprovalEngine $approvalEngine,
    ) {}

    /**
     * Create a new purchase request with snapshot product data.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, string $branchCode, ?int $userId = null, ?string $userName = null): PurchaseRequest
    {
        $variants = collect($this->purchaseVariants->execute())->keyBy('id');
        $creatorId = $userId ?? (int) auth()->id();
        $creatorName = $userName ?? auth()->user()?->name;

        return DB::transaction(function () use ($data, $branchCode, $variants, $creatorId, $creatorName) {
            $sequence = PurchaseRequest::where('branch_id', $data['branch_id'])->count() + 1;
            $number = 'PR-'.$branchCode.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            $subtotal = 0.0;
            $taxAmount = 0.0;

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty_requested'];
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $subtotal += $qty * $unitPrice;
            }

            $taxRate = 0.0;
            if (($data['items'][0]['tax_id'] ?? null) !== null) {
                $taxRate = $this->resolveTaxRate((int) $data['items'][0]['tax_id']);
            }
            $taxAmount = $subtotal * ($taxRate / 100);
            $total = $subtotal + $taxAmount;

            $request = PurchaseRequest::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'status' => 'pending',
                'request_date' => $data['request_date'],
                'expected_date' => $data['expected_date'] ?? null,
                'note' => $data['note'] ?? null,
                'currency_code' => 'IDR',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            foreach ($data['items'] as $item) {
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
                    'tax_rate' => ($item['tax_id'] ?? null) !== null ? $taxRate : null,
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

            $finalStatus = $mapping ? 'pending' : 'approved';
            $request->update(['status' => $finalStatus]);

            return $request->load('items');
        });
    }

    private function resolveTaxRate(int $taxId): float
    {
        $tax = $this->getPurchaseTaxes->execute();

        foreach ($tax as $item) {
            if ($item['id'] === $taxId) {
                return (float) $item['rate'];
            }
        }

        return 0.0;
    }
}
