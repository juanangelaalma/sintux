<?php

namespace Modules\Contact\Application;

use Illuminate\Support\Facades\DB;

class ContactBranchCounts
{
    /**
     * Contact counts grouped by branch, for the given branch ids.
     *
     * @param  list<int>  $branchIds
     * @return array<int, array{customers: int, suppliers: int, employees: int}>
     */
    public function execute(array $branchIds): array
    {
        if ($branchIds === [] || ! tenancy()->initialized) {
            return [];
        }

        $rows = DB::table('contacts')
            ->whereIn('branch_id', $branchIds)
            ->selectRaw('branch_id, type, COUNT(*) as total')
            ->groupBy('branch_id', 'type')
            ->get();

        $counts = [];

        foreach ($rows as $row) {
            $counts[(int) $row->branch_id][$row->type] = (int) $row->total;
        }

        $result = [];

        foreach ($counts as $branchId => $byType) {
            $result[$branchId] = [
                'customers' => $byType['customer'] ?? 0,
                'suppliers' => $byType['supplier'] ?? 0,
                'employees' => $byType['employee'] ?? 0,
            ];
        }

        return $result;
    }
}
