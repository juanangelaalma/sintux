<?php

namespace Modules\Product\Application\Uom;

use Modules\Product\Models\Uom;

class DeleteUom
{
    public function execute(int $id): bool
    {
        $uom = Uom::find($id);

        if (! $uom) {
            return false;
        }

        return $uom->delete();
    }
}