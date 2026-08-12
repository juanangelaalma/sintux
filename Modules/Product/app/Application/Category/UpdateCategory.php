<?php

namespace Modules\Product\Application\Category;

use Modules\Product\Models\ProductCategory;

class UpdateCategory
{
    public function execute(int $id, array $data): ?ProductCategory
    {
        $category = ProductCategory::find($id);

        if (! $category) {
            return null;
        }

        $category->update([
            'name' => $data['name'] ?? $category->name,
            'is_active' => $data['is_active'] ?? $category->is_active,
        ]);

        return $category;
    }
}
