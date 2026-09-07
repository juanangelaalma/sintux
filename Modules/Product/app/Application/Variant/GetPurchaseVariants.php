<?php

namespace Modules\Product\Application\Variant;

use Modules\Product\Models\ProductVariant;

class GetPurchaseVariants
{
    /**
     * Get active product variants for purchase documents.
     *
     * Public cross-module API: exposes only the fields a purchasing
     * document needs to snapshot at creation (id, product_name, sku,
     * uom_name) and never leaks the full Product model.
     *
     * @param  list<int>|null  $branchIds  Branch scope. Null = all.
     * @return list<array{id: int, product_id: int, branch_id: int, product_name: string, sku: string, uom_name: string|null}>
     */
    public function execute(?array $branchIds = null): array
    {
        $query = ProductVariant::with(['product.uom'])
            ->where('is_active', true)
            ->whereHas('product', fn ($q) => $q->where('is_active', true));

        if (! empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

        return array_values($query
            ->orderBy('variant_name')
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'product_id' => $variant->product_id,
                'branch_id' => (int) $variant->branch_id,
                'product_name' => $variant->product ? $variant->product->name : '',
                'sku' => $variant->sku,
                'uom_name' => $variant->product?->uom?->name,
            ])
            ->all());
    }
}
