<?php

namespace Modules\Warehouse\Application\Warehouse;

use Modules\Warehouse\Models\Warehouse;

class CreateWarehouse
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): Warehouse
    {
        return Warehouse::create([
            'branch_id' => $data['branch_id'],
            'code' => $data['code'],
            'name' => $data['name'],
            'warehouse_type' => $data['warehouse_type'],
            'address' => $data['address'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
