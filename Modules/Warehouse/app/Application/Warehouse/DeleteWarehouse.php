<?php

namespace Modules\Warehouse\Application\Warehouse;

use Modules\Warehouse\Models\Warehouse;

class DeleteWarehouse
{
    /**
     * @param  list<int>  $branchIds
     */
    public function execute(int $id, array $branchIds): bool
    {
        $warehouse = Warehouse::whereIn('branch_id', $branchIds)->find($id);

        if (! $warehouse) {
            return false;
        }

        if ($warehouse->stockBalances()->where('qty_on_hand', '>', 0)->exists()) {
            return false;
        }

        return (bool) $warehouse->delete();
    }
}
