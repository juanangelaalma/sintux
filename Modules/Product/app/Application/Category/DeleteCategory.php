<?php

namespace Modules\Product\Application\Category;

use Modules\Product\Models\ProductCategory;

class DeleteCategory
{
    public function execute(int $id): bool
    {
        $category = ProductCategory::find($id);

        if (! $category) {
            return false;
        }

        return $category->delete();
    }
}