<?php

namespace Modules\Product\Application\Variant;

use Modules\Product\Models\ProductVariant;

class DeleteVariant
{
    public function execute(int $id): bool
    {
        $variant = ProductVariant::find($id);

        if (! $variant) {
            return false;
        }

        return $variant->delete();
    }
}