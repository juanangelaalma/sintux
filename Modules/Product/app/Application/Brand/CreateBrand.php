<?php

namespace Modules\Product\Application\Brand;

use Modules\Product\Models\Brand;

class CreateBrand
{
    public function execute(array $data): Brand
    {
        return Brand::create([
            'name' => $data['name'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}