<?php

namespace Modules\Product\Application\Uom;

use Modules\Product\Models\Uom;

class UpdateUom
{
    public function execute(int $id, array $data): ?Uom
    {
        $uom = Uom::find($id);

        if (! $uom) {
            return null;
        }

        $uom->update([
            'name' => $data['name'] ?? $uom->name,
            'code' => $data['code'] ?? $uom->code,
            'is_active' => $data['is_active'] ?? $uom->is_active,
        ]);

        return $uom;
    }
}