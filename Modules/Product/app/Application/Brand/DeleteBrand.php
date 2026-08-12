<?php

namespace Modules\Product\Application\Brand;

use Modules\Product\Models\Brand;

class DeleteBrand
{
    public function execute(int $id): bool
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return false;
        }

        return $brand->delete();
    }
}