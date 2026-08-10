<?php

namespace App\Application;

use Modules\Company\Models\Branch;
use Modules\Contact\Application\ContactBranchCounts;

class GetDashboard
{
    public function __construct(
        private readonly ContactBranchCounts $contactBranchCounts,
    ) {}

    /**
     * Branch summaries for the dashboard, scoped to accessible branches.
     *
     * @param  list<int>  $branchIds
     * @return list<array<string, mixed>>
     */
    public function execute(array $branchIds): array
    {
        if ($branchIds === [] || ! tenancy()->initialized) {
            return [];
        }

        $counts = $this->contactBranchCounts->execute($branchIds);

        return array_values(Branch::query()
            ->whereIn('id', $branchIds)
            ->orderByDesc('is_headquarters')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'is_headquarters' => (bool) $branch->is_headquarters,
                'contacts' => $counts[$branch->id] ?? [
                    'customers' => 0,
                    'suppliers' => 0,
                    'employees' => 0,
                ],
            ])
            ->all());
    }
}
