<?php

namespace Modules\Sales\Application\SalesInvoice;

use Modules\Sales\Models\SalesInvoice;

class GetSalesInvoiceDetail
{
    /**
     * @param  list<int>  $branchIds
     */
    public function execute(int $id, array $branchIds): SalesInvoice
    {
        return SalesInvoice::with(['items'])
            ->whereIn('branch_id', $branchIds)
            ->findOrFail($id);
    }
}
