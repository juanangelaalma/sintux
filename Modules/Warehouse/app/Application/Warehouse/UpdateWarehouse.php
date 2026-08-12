<?php

namespace Modules\Warehouse\Application\Warehouse;

use Modules\Warehouse\Models\Warehouse;

class UpdateWarehouse
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $branchIds
     */
    public function execute(int $id, array $data, array $branchIds): ?Warehouse
    {
        $warehouse = Warehouse::whereIn('branch_id', $branchIds)->find($id);

        if (! $warehouse) {
            return null;
        }

        $warehouse->update([
            'branch_id' => $data['branch_id'] ?? $warehouse->branch_id,
            'code' => $data['code'] ?? $warehouse->code,
            'name' => $data['name'] ?? $warehouse->name,
            'warehouse_type' => $data['warehouse_type'] ?? $warehouse->warehouse_type,
            'address' => $data['address'] ?? $warehouse->address,
            'is_active' => $data['is_active'] ?? $warehouse->is_active,
        ]);

        return $warehouse;
    }
}
