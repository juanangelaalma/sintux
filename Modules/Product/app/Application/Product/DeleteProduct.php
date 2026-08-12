<?php

namespace Modules\Product\Application\Product;

use Modules\Product\Models\Product;

class DeleteProduct
{
    public function execute(int $id): bool
    {
        $product = Product::find($id);

        if (! $product) {
            return false;
        }

        if ($product->variants()->where('is_active', true)->exists()) {
            return false;
        }

        return $product->delete();
    }
}
