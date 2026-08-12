<?php

namespace Modules\Product\Application\Uom;

use Modules\Product\Models\Uom;

class GetUom
{
    public function execute(int $id): ?Uom
    {
        return Uom::find($id);
    }
}