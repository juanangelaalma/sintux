<?php

namespace Modules\Product\Application\Brand;

use Modules\Product\Models\Brand;

class UpdateBrand
{
    public function execute(int $id, array $data): ?Brand
    {
        $brand = Brand::find($id);

        if (! $brand) {
            return null;
        }

        $brand->update([
            'name' => $data['name'] ?? $brand->name,
            'is_active' => $data['is_active'] ?? $brand->is_active,
        ]);

        return $brand;
    }
}
