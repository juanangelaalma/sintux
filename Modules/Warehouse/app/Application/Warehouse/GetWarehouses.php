<?php

namespace Modules\Warehouse\Application\Warehouse;

use Modules\Warehouse\Models\Warehouse;

class GetWarehouses
{
    /**
     * @param  list<int>  $branchIds
     * @param  array<string, mixed>  $filters
     */
    public function execute(array $branchIds, array $filters = []): array
    {
        $query = Warehouse::with('branch')->whereIn('branch_id', $branchIds);

        if (! empty($filters['search'])) {
            $query->where(function ($query) use ($filters): void {
                $query->where('code', 'like', '%'.$filters['search'].'%')
                    ->orWhere('name', 'like', '%'.$filters['search'].'%');
            });
        }

        if (! empty($filters['warehouse_type'])) {
            $query->where('warehouse_type', $filters['warehouse_type']);
        }

        return $query->orderBy('name')->paginate(15)->toArray();
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<array{id: int, name: string}>
     */
    public function all(array $branchIds, bool $activeOnly = true): array
    {
        $query = Warehouse::whereIn('branch_id', $branchIds);

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return $query->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Warehouse $warehouse) => [
                'id' => (int) $warehouse->id,
                'name' => $warehouse->name,
            ])
            ->all();
    }

    /**
     * Flat options for goods receipt: active regular warehouses of HQ only.
     *
     * @return list<array{id: int, code: string, name: string}>
     */
    public function optionsForReceipt(int $hqBranchId): array
    {
        return Warehouse::where('branch_id', $hqBranchId)
            ->where('is_active', true)
            ->where('warehouse_type', 'regular')
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Warehouse $warehouse) => [
                'id' => (int) $warehouse->id,
                'code' => (string) $warehouse->code,
                'name' => (string) $warehouse->name,
            ])
            ->all();
    }

    /**
     * @param  list<int>  $branchIds
     * @return list<int>
     */
    public function warehouseIdsByBranches(array $branchIds): array
    {
        return Warehouse::whereIn('branch_id', $branchIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  list<int>  $branchIds
     */
    public function countByBranchIds(array $branchIds): int
    {
        return Warehouse::whereIn('branch_id', $branchIds)->count();
    }
}
