<?php

namespace Modules\Product\Application\Product;

use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

class UpdateProduct
{
    public function execute(int $id, array $data): ?Product
    {
        $product = Product::find($id);

        if (! $product) {
            return null;
        }

        return DB::transaction(function () use ($product, $data) {
            $product->update([
                'code' => $data['code'] ?? $product->code,
                'name' => $data['name'] ?? $product->name,
                'barcode' => array_key_exists('barcode', $data) ? $data['barcode'] : $product->barcode,
                'category_id' => $data['category_id'] ?? $product->category_id,
                'uom_id' => $data['uom_id'] ?? $product->uom_id,
                'description' => array_key_exists('description', $data) ? $data['description'] : $product->description,
                'image_path' => array_key_exists('image_path', $data) ? $data['image_path'] : $product->image_path,
                'product_type' => $data['product_type'] ?? $product->product_type,
                'is_purchased' => $data['is_purchased'] ?? $product->is_purchased,
                'purchase_price' => $data['purchase_price'] ?? $product->purchase_price,
                'purchase_account_id' => array_key_exists('purchase_account_id', $data) ? $data['purchase_account_id'] : $product->purchase_account_id,
                'purchase_tax_id' => array_key_exists('purchase_tax_id', $data) ? $data['purchase_tax_id'] : $product->purchase_tax_id,
                'is_sold' => $data['is_sold'] ?? $product->is_sold,
                'selling_price' => $data['selling_price'] ?? $product->selling_price,
                'sales_account_id' => array_key_exists('sales_account_id', $data) ? $data['sales_account_id'] : $product->sales_account_id,
                'sales_tax_id' => array_key_exists('sales_tax_id', $data) ? $data['sales_tax_id'] : $product->sales_tax_id,
                'is_inventory_tracked' => $data['is_inventory_tracked'] ?? $product->is_inventory_tracked,
                'min_stock' => $data['min_stock'] ?? $product->min_stock,
                'inventory_account_id' => array_key_exists('inventory_account_id', $data) ? $data['inventory_account_id'] : $product->inventory_account_id,
                'is_active' => $data['is_active'] ?? $product->is_active,
            ]);

            // Auto-sync primary variant for 1:1 Warehouse compatibility
            ProductVariant::updateOrCreate(
                ['product_id' => $product->id],
                [
                    'branch_id' => $product->branch_id,
                    'sku' => $product->code,
                    'variant_name' => $product->name,
                    'is_active' => $product->is_active,
                ]
            );

            // Sync bundle items if bundle
            if ($product->product_type === 'bundle' && isset($data['bundle_items'])) {
                $product->bundleItems()->delete();
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
