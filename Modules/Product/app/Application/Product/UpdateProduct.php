<?php

namespace Modules\Product\Application\Product;

use Modules\Product\Models\Product;

class UpdateProduct
{
    public function execute(int $id, array $data): ?Product
    {
        $product = Product::find($id);

        if (! $product) {
            return null;
        }

        $product->update([
            'code' => $data['code'] ?? $product->code,
            'name' => $data['name'] ?? $product->name,
            'category_id' => $data['category_id'] ?? $product->category_id,
            'brand_id' => $data['brand_id'] ?? $product->brand_id,
            'uom_id' => $data['uom_id'] ?? $product->uom_id,
            'description' => $data['description'] ?? $product->description,
            'is_active' => $data['is_active'] ?? $product->is_active,
        ]);

        return $product;
    }
}
