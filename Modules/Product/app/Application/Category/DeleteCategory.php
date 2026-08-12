<?php

namespace Modules\Product\Application\Category;

use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;

class DeleteCategory
{
    public function execute(int $id): bool
    {
        $category = ProductCategory::find($id);

        if (! $category) {
            return false;
        }

        if (Product::withTrashed()->where('category_id', $id)->exists()) {
            return false;
        }

        return $category->delete();
    }
}
