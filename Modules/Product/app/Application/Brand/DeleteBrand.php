<?php

namespace Modules\Product\Application\Brand;

use Modules\Product\Models\Brand;
use Modules\Product\Models\Product;

class DeleteBrand
{
    public function execute(int $id): bool
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return false;
        }

        if (Product::withTrashed()->where('brand_id', $id)->exists()) {
            return false;
        }

        return $brand->delete();
    }
}
