<?php

namespace Modules\Product\Application\Variant;

use Modules\Product\Models\ProductVariant;

class UpdateVariant
{
    public function execute(int $id, array $data): ?ProductVariant
    {
        $variant = ProductVariant::find($id);

        if (! $variant) {
            return null;
        }

        $variant->update([
            'sku' => $data['sku'] ?? $variant->sku,
            'variant_name' => $data['variant_name'] ?? $variant->variant_name,
            'attributes' => $data['attributes'] ?? $variant->attributes,
            'is_active' => $data['is_active'] ?? $variant->is_active,
        ]);

        return $variant;
    }
}