<?php

namespace Modules\Purchasing\Application\PurchaseTag;

use Illuminate\Support\Collection;
use Modules\Purchasing\Models\PurchaseTag;

class GetPurchaseTags
{
    /**
     * @return Collection<int, array{id: int, name: string, color: string|null}>
     */
    public function execute(): Collection
    {
        return PurchaseTag::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (PurchaseTag $tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color' => $tag->color,
            ]);
    }
}
