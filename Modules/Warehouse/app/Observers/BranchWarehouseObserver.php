<?php

namespace Modules\Warehouse\Observers;

use Modules\Company\Models\Branch;
use Modules\Warehouse\Application\Warehouse\CreateWarehousesForBranch;

class BranchWarehouseObserver
{
    public function created(Branch $branch): void
    {
        app(CreateWarehousesForBranch::class)->execute(
            (int) $branch->id,
            (string) $branch->code,
            (string) $branch->name
        );
    }
}
