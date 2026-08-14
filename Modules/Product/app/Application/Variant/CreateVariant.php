<?php

namespace Modules\Product\Application\Variant;

use Modules\Product\Models\ProductVariant;

class CreateVariant
{
    public function execute(array $data): ProductVariant
    {
        return ProductVariant::create([
            'product_id' => $data['product_id'],
            'sku' => $data['sku'],
            'variant_name' => $data['variant_name'],
            'attributes' => $data['attributes'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
