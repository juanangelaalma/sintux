<?php

namespace Modules\Product\Application\Product;

use Modules\Product\Models\Product;

class CreateProduct
{
    public function execute(array $data): Product
    {
        return Product::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'category_id' => $data['category_id'],
            'brand_id' => $data['brand_id'] ?? null,
            'uom_id' => $data['uom_id'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
