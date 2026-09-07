<?php

namespace Modules\Product\Application\Product;

use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;
use Modules\Warehouse\Application\StockBalance\GetStockBalances;

class GetProducts
{
    public function __construct(
        private readonly GetStockBalances $getStockBalances,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @param  list<int>|null  $branchIds  Branch scope for filtering products and stock. Null = all.
     * @return array<string, mixed>
     */
    public function execute(array $filters = [], ?array $branchIds = null): array
    {
        $query = Product::with([
            'category',
            'uom',
            'variants' => fn ($q) => $q->where('is_active', true),
            'bundleItems.itemProduct.variants' => fn ($q) => $q->where('is_active', true),
        ]);

        if (! empty($branchIds)) {
            $query->whereIn('branch_id', $branchIds);
        }

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

        $paginator = $query->orderBy('name')->paginate(15);

        $stocks = $this->getStockBalances->totalQtyByVariantIds(
            $paginator->getCollection()
                ->flatMap(fn (Product $product) => $product->variants->pluck('id'))
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->values()
                ->all(),
            $branchIds,
        );

        $paginator->getCollection()->transform(function (Product $product) use ($stocks): Product {
            $product->setAttribute('total_stock', $this->productTotalStock($product, $stocks));

            return $product;
        });

        return $paginator->toArray();
    }

    /**
     * @param  array<int, int>  $stocks  Variant id => total qty on hand.
     */
    private function productTotalStock(Product $product, array $stocks): int
    {
        if ($product->product_type === 'bundle') {
            $bundleQty = null;

            foreach ($product->bundleItems as $item) {
                if (! $item->itemProduct) {
                    continue;
                }

                $componentStock = $item->itemProduct->variants->sum(
                    fn (ProductVariant $variant) => $stocks[$variant->id] ?? 0
                );

                $needed = max(1, (float) ($item->quantity ?? 1));
                $bundles = (int) floor($componentStock / $needed);

                $bundleQty = $bundleQty === null ? $bundles : min($bundleQty, $bundles);
            }

            return $bundleQty ?? 0;
        }

        return $product->variants->sum(
            fn (ProductVariant $variant) => $stocks[$variant->id] ?? 0
        );
    }
}
