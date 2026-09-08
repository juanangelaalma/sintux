<?php

namespace Modules\Purchasing\Application\GoodsReceipt;

use Illuminate\Support\Facades\DB;
use Modules\Product\Application\Variant\GetPurchaseVariants;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Models\GoodsReceipt;

class CreateGoodsReceipt
{
    public function __construct(
        private readonly GetPurchaseVariants $purchaseVariants,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>|null  $branchIds  Branch scope for variant resolution. Null = all.
     */
    public function execute(array $data, string $branchCode, ?array $branchIds = null): GoodsReceipt
    {
        $variants = collect($this->purchaseVariants->execute($branchIds))->keyBy('id');

        return DB::transaction(function () use ($data, $branchCode, $variants) {
            $sequence = GoodsReceipt::where('branch_id', $data['branch_id'])->count() + 1;
            $number = 'GRN-'.$branchCode.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);

            $grn = GoodsReceipt::create([
                'number' => $number,
                'branch_id' => $data['branch_id'],
                'supplier_id' => $data['supplier_id'],
                'purchase_order_id' => $data['purchase_order_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status' => GoodsReceiptStatus::Draft,
                'receipt_date' => $data['receipt_date'],
                'note' => $data['note'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                $variant = $variants->get($item['product_variant_id']);

                $grn->items()->create([
                    'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                    'product_variant_id' => $item['product_variant_id'],
                    'product_name' => $variant['product_name'] ?? '',
                    'sku' => $variant['sku'] ?? '',
                    'uom_name' => $variant['uom_name'] ?? null,
                    'qty_received' => $item['qty_received'],
                ]);
            }

            return $grn->load('items');
        });
    }
}
