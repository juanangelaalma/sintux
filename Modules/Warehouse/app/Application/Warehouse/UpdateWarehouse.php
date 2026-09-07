<?php

namespace Modules\Warehouse\Application\Warehouse;

use Illuminate\Validation\ValidationException;
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

        // System warehouses cannot be deactivated
        if (array_key_exists('is_active', $data) && $data['is_active'] === false) {
            if (in_array($warehouse->warehouse_type, ['regular', 'retail', 'consignment'], true) && str_starts_with($warehouse->code, 'GD-')) {
                throw ValidationException::withMessages([
                    'warehouse' => 'Gudang sistem (Regular/Ritel/Konsinyasi) tidak dapat dinonaktifkan.',
                ]);
            }
        }

        // System warehouses code/type should not be changed arbitrarily — allow name/address only
        if (in_array($warehouse->warehouse_type, ['regular', 'retail', 'consignment'], true) && str_starts_with($warehouse->code, 'GD-')) {
            if (isset($data['code']) && $data['code'] !== $warehouse->code) {
                throw ValidationException::withMessages([
                    'code' => 'Kode gudang sistem tidak dapat diubah.',
                ]);
            }
            if (isset($data['warehouse_type']) && $data['warehouse_type'] !== $warehouse->warehouse_type) {
                throw ValidationException::withMessages([
                    'warehouse_type' => 'Tipe gudang sistem tidak dapat diubah.',
                ]);
            }
            if (isset($data['branch_id']) && (int) $data['branch_id'] !== (int) $warehouse->branch_id) {
                throw ValidationException::withMessages([
                    'branch_id' => 'Cabang gudang sistem tidak dapat dipindah.',
                ]);
            }
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
