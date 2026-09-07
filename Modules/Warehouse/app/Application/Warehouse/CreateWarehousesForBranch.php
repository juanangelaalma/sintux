<?php

namespace Modules\Warehouse\Application\Warehouse;

use Illuminate\Support\Facades\DB;
use Modules\Warehouse\Models\Warehouse;

class CreateWarehousesForBranch
{
    /**
     * Create 3 system warehouses for a branch (REG/RIT/KON) with code GD-{BranchCode}-{TYPE}.
     * Idempotent: skips if already exists for that branch+type.
     *
     * @return list<Warehouse>
     */
    public function execute(int $branchId, string $branchCode, string $branchName): array
    {
        $definitions = [
            'regular' => ['code_suffix' => 'REG', 'name' => 'Gudang Regular'],
            'retail' => ['code_suffix' => 'RIT', 'name' => 'Gudang Ritel'],
            'consignment' => ['code_suffix' => 'KON', 'name' => 'Gudang Konsinyasi'],
        ];

        $created = [];

        foreach ($definitions as $type => $def) {
            $code = sprintf('GD-%s-%s', $branchCode, $def['code_suffix']);
            $name = $def['name'].' '.$branchName;

            $existing = Warehouse::where('branch_id', $branchId)
                ->where('warehouse_type', $type)
                ->first();

            if ($existing) {
                // Ensure code/name are correct (fix drift)
                if ($existing->code !== $code || $existing->name !== $name) {
                    $existing->update(['code' => $code, 'name' => $name]);
                }
                $created[] = $existing;

                continue;
            }

            // Check code collision (global unique)
            $codeExists = Warehouse::where('code', $code)->exists();
            if ($codeExists) {
                // If collision due to old data, make unique by suffix
                $code = sprintf('GD-%s-%s-%s', $branchCode, $def['code_suffix'], substr(uniqid(), -4));
            }

            $warehouse = Warehouse::create([
                'branch_id' => $branchId,
                'code' => $code,
                'name' => $name,
                'warehouse_type' => $type,
                'is_active' => true,
            ]);

            $created[] = $warehouse;
        }

        return $created;
    }

    /**
     * Ensure all existing branches have 3 system warehouses (for migrate:fresh or backfill).
     */
    public function ensureForAllBranches(): void
    {
        $branches = DB::table('branches')->get(['id', 'code', 'name']);

        foreach ($branches as $branch) {
            $this->execute((int) $branch->id, (string) $branch->code, (string) $branch->name);
        }
    }
}
