<?php

namespace Modules\Purchasing\Application\PurchaseInvoice;

use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\GetPurchaseTaxes;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Models\PurchaseInvoice;

class CreatePurchaseInvoice
{
    public function __construct(
        private readonly GetPurchaseVariants $purchaseVariants,
        private readonly GetPurchaseTaxes $getPurchaseTaxes,
        private readonly ApprovalEngine $approvalEngine,
        private readonly ValidateInvoiceQuantities $validateInvoiceQuantities,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data, string $branchCode, ?int $userId = null, ?string $userName = null): PurchaseInvoice
    {
        $variants = collect($this->purchaseVariants->execute())->keyBy('id');
        $taxes = collect($this->getPurchaseTaxes->execute())->keyBy('id');
        $creatorId = $userId ?? (int) auth()->id();
        $creatorName = $userName ?? auth()->user()?->name;

        return DB::transaction(function () use ($data, $branchCode, $variants, $taxes, $creatorId, $creatorName) {
            $sequence = PurchaseInvoice::where('branch_id', $data['branch_id'])->count() + 1;
            $number = 'INV-'.$branchCode.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            $subtotal = 0.0;
            $taxAmount = 0.0;

            foreach ($data['items'] as $item) {
                $qty = (float) $item['qty'];
                $unitPrice = (float) $item['unit_price'];
                $lineSubtotal = $qty * $unitPrice;
                $subtotal += $lineSubtotal;

                $taxId = $item['tax_id'] ?? null;
                $taxRate = $taxId ? (float) ($taxes->get($taxId)['rate'] ?? 0) : 0.0;
                $taxAmount += $lineSubtotal * ($taxRate / 100);
            }

            $total = $subtotal + $taxAmount;

            $inv = PurchaseInvoice::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'status' => 'pending',
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'note' => $data['note'] ?? null,
                'currency_code' => 'IDR',
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'total' => $total,
            ]);

            foreach ($data['items'] as $item) {
                $variant = $variants->get($item['product_variant_id']);
                $qty = (float) $item['qty'];
                $unitPrice = (float) $item['unit_price'];
                $taxId = $item['tax_id'] ?? null;
                $taxRate = $taxId ? (float) ($taxes->get($taxId)['rate'] ?? 0) : 0.0;
                $lineTotal = ($qty * $unitPrice) * (1 + ($taxRate / 100));

                $inv->items()->create([
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'product_variant_id' => $item['product_variant_id'],
                    'product_name' => $variant['product_name'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'uom_name' => $variant['uom_name'] ?? null,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_id' => $taxId,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotal,
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
                // Auto-final: run 3-way match validation now
                $this->validateInvoiceQuantities->execute($inv);
                $inv->update(['status' => 'approved']);
            } else {
                $inv->update(['status' => 'pending']);
            }

            return $inv->load('items');
        });
    }
}
