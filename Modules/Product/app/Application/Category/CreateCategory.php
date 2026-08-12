<?php

namespace Modules\Product\Application\Category;

use Modules\Product\Models\ProductCategory;

class CreateCategory
{
    public function execute(array $data): ProductCategory
    {
        return ProductCategory::create([
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}