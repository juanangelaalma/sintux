<?php

namespace Modules\Expense\Application\Expense;

use Modules\Expense\Models\Expense;

/**
 * Data detail biaya untuk halaman detail: header, baris, tag, dan lampiran.
 */
class GetExpenseDetail
{
    /**
     * @param  list<int>  $branchIds
     */
    public function execute(int $id, array $branchIds): Expense
    {
        return Expense::query()
            ->with(['lines', 'tags', 'attachments'])
            ->whereIn('branch_id', $branchIds)
            ->findOrFail($id);
    }
}
