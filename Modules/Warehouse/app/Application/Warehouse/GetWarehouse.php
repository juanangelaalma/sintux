<?php

namespace Modules\Warehouse\Application\Warehouse;

use Modules\Warehouse\Models\Warehouse;

class GetWarehouse
{
    /**
     * @param  list<int>  $branchIds
     */
    public function execute(int $id, array $branchIds): ?Warehouse
    {
        return Warehouse::with('branch')
            ->whereIn('branch_id', $branchIds)
            ->find($id);
    }
}
