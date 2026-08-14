<?php

namespace Modules\Product\Application\Product;

use Modules\Product\Models\Product;

class GetProduct
{
    public function execute(int $id): ?Product
    {
        return Product::with([
            'category',
            'uom',
            'bundleItems.itemProduct.uom',
        ])->find($id);
    }
}
