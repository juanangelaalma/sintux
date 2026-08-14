<?php

namespace Modules\Product\Application\Brand;

use Modules\Product\Models\Brand;

class GetBrands
{
    public function execute(array $filters = []): array
    {
        $query = Brand::query();

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%');
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('name')->paginate(15)->toArray();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function all(bool $activeOnly = true): array
    {
        $query = Brand::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($brand) => ['id' => (int) $brand->id, 'name' => $brand->name])
            ->all();
    }
}
