<?php

namespace Modules\Warehouse\Application\Warehouse;

use Illuminate\Validation\ValidationException;
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

        // System warehouses (Regular/Ritel/Konsinyasi) cannot be deleted
        if (in_array($warehouse->warehouse_type, ['regular', 'retail', 'consignment'], true)) {
            // Check if it's one of the 3 system warehouses per branch (code pattern GD-*-REG/RIT/KON)
            if (str_starts_with($warehouse->code, 'GD-')) {
                throw ValidationException::withMessages([
                    'warehouse' => 'Gudang sistem (Regular/Ritel/Konsinyasi) tidak dapat dihapus.',
                ]);
            }
        }

        if ($warehouse->stockBalances()->where('qty_on_hand', '>', 0)->exists()) {
            return false;
        }

        return (bool) $warehouse->delete();
    }
}
