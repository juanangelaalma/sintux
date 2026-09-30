<?php

namespace Modules\Expense\Application\ExpenseTag;

use Illuminate\Support\Collection;
use Modules\Expense\Models\ExpenseTag;

/**
 * Daftar tag biaya untuk kolom 7 form dan kolom Tags di daftar.
 */
class GetExpenseTags
{
    /**
     * @return Collection<int, array{id: int, name: string, color: string|null}>
     */
    public function execute(): Collection
    {
        return ExpenseTag::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (ExpenseTag $tag): array => [
                'id' => (int) $tag->id,
                'name' => (string) $tag->name,
                'color' => $tag->color,
            ])
            ->values();
    }
}
