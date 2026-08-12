<?php

namespace Modules\Product\Application\Uom;

use Modules\Product\Models\Uom;

class GetUoms
{
    public function execute(array $filters = []): array
    {
        $query = Uom::query();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('code', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        return $query->orderBy('name')->paginate(15)->toArray();
    }

    /**
     * @return list<array{id: int, name: string, code: string}>
     */
    public function all(bool $activeOnly = true): array
    {
        $query = Uom::query();

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')
            ->get(['id', 'name', 'code'])
            ->map(fn ($uom) => ['id' => (int) $uom->id, 'name' => $uom->name, 'code' => $uom->code])
            ->all();
    }
}