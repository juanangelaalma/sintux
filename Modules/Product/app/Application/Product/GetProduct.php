<?php

namespace Modules\Product\Application\Product;

use Modules\Product\Models\Product;

class GetProduct
{
    public function execute(int $id): ?Product
    {
        return Product::with(['category', 'brand', 'uom'])->find($id);
    }
}
