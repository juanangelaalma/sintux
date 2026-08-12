<?php

namespace Modules\Product\Application\Uom;

use Modules\Product\Models\Uom;

class CreateUom
{
    public function execute(array $data): Uom
    {
        return Uom::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'is_active' => $data['is_active'] ?? true,
        ]);
    }
}
