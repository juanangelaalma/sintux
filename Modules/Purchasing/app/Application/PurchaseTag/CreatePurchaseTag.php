<?php

namespace Modules\Purchasing\Application\PurchaseTag;

use Modules\Purchasing\Models\PurchaseTag;

class CreatePurchaseTag
{
    /**
     * @return array{id: int, name: string, color: string|null}
     */
    public function execute(string $name): array
    {
        $tag = PurchaseTag::query()->firstOrCreate([
            'name' => $name,
        ]);

        return [
            'id' => $tag->id,
            'name' => $tag->name,
            'color' => $tag->color,
        ];
    }
}
