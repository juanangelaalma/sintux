<?php

namespace Modules\Product\Application\Product;

use Modules\Product\Models\Product;

class GetProducts
{
    public function execute(array $filters = []): array
    {
        $query = Product::with([
            'category',
            'uom',
            'variants' => fn ($q) => $q->where('is_active', true),
            'bundleItems.itemProduct',
        ]);

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('code', 'like', '%'.$filters['search'].'%')
                    ->orWhere('name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('barcode', 'like', '%'.$filters['search'].'%');
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        if (! empty($filters['product_type'])) {
            $query->where('product_type', $filters['product_type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('name')->paginate(15)->toArray();
    }
}
