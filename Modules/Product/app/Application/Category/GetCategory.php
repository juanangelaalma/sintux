<?php

namespace Modules\Product\Application\Category;

use Modules\Product\Models\ProductCategory;

class GetCategory
{
    public function execute(int $id): ?ProductCategory
    {
        return ProductCategory::find($id);
    }
}
