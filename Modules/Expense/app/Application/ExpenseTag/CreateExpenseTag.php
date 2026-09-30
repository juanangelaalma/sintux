<?php

namespace Modules\Expense\Application\ExpenseTag;

use Modules\Expense\Models\ExpenseTag;

/**
 * Buat tag biaya dari ketikan langsung di kolom 7 form. firstOrCreate
 * supaya nama yang sama tidak menghasilkan duplikat.
 */
class CreateExpenseTag
{
    /**
     * @return array{id: int, name: string, color: string|null}
     */
    public function execute(string $name): array
    {
        $tag = ExpenseTag::query()->firstOrCreate(
            ['name' => $name],
            ['color' => null],
        );

        return [
            'id' => (int) $tag->id,
            'name' => (string) $tag->name,
            'color' => $tag->color,
        ];
    }
}
