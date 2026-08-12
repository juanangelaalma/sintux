<?php

namespace Modules\Product\Application\Brand;

use Modules\Product\Models\Brand;

class GetBrand
{
    public function execute(int $id): ?Brand
    {
        return Brand::find($id);
    }
}
