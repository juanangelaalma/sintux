<?php

namespace Modules\Product\Application\Product;

use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

class CreateProduct
{
    public function execute(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'barcode' => $data['barcode'] ?? null,
                'category_id' => $data['category_id'],
                'uom_id' => $data['uom_id'],
                'description' => $data['description'] ?? null,
                'image_path' => $data['image_path'] ?? null,
                'product_type' => $data['product_type'] ?? 'single',
                'is_purchased' => $data['is_purchased'] ?? true,
                'purchase_price' => $data['purchase_price'] ?? 0,
                'purchase_account_id' => $data['purchase_account_id'] ?? null,
                'purchase_tax_id' => $data['purchase_tax_id'] ?? null,
                'is_sold' => $data['is_sold'] ?? true,
                'selling_price' => $data['selling_price'] ?? 0,
                'sales_account_id' => $data['sales_account_id'] ?? null,
                'sales_tax_id' => $data['sales_tax_id'] ?? null,
                'is_inventory_tracked' => $data['is_inventory_tracked'] ?? true,
                'min_stock' => $data['min_stock'] ?? 0,
                'inventory_account_id' => $data['inventory_account_id'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            // Auto-sync primary variant for 1:1 Warehouse compatibility
            ProductVariant::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'sku' => $product->code,
                    'variant_name' => $product->name,
                    'is_active' => $product->is_active,
                ]
            );

            // Save bundle items if product_type is bundle
            if (($data['product_type'] ?? 'single') === 'bundle' && ! empty($data['bundle_items'])) {
                foreach ($data['bundle_items'] as $item) {
                    $product->bundleItems()->create([
                        'item_product_id' => $item['item_product_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            }

            return $product;
        });
    }
}
