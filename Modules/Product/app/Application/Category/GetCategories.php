<?php

namespace Modules\Product\Application\Category;

use Modules\Product\Models\ProductCategory;

class GetCategories
{
    public function execute(array $filters = []): array
    {
        $query = ProductCategory::withCount('products');

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('name')->paginate(50)->toArray();
    }

    /**
     * @return list<array{id: int, name: string, products_count?: int}>
     */
    public function all(bool $activeOnly = true): array
    {
        $query = ProductCategory::withCount('products');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')
            ->get()
            ->map(fn ($category) => [
                'id' => (int) $category->id,
                'name' => $category->name,
                'products_count' => (int) ($category->products_count ?? 0),
            ])
            ->all();
    }
}
