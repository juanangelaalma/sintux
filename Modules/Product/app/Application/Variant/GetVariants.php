<?php

namespace Modules\Product\Application\Variant;

use Modules\Product\Models\ProductVariant;

class GetVariants
{
    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>|null  $branchIds  Branch scope. Null = all.
     */
    public function execute(int $productId, array $filters = [], ?array $branchIds = null): array
    {
        $query = ProductVariant::where('product_id', $productId);

        if (! empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('sku', 'like', '%'.$filters['search'].'%')
                    ->orWhere('variant_name', 'like', '%'.$filters['search'].'%');
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('variant_name')->paginate(15)->toArray();
    }
}
