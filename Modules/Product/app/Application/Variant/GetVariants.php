<?php

namespace Modules\Product\Application\Variant;

use Modules\Product\Models\ProductVariant;

class GetVariants
{
    public function execute(int $productId, array $filters = []): array
    {
        $query = ProductVariant::where('product_id', $productId);

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('sku', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('variant_name', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('variant_name')->paginate(15)->toArray();
    }
}