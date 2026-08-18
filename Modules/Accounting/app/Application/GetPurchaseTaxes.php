<?php

namespace Modules\Accounting\Application;

use Modules\Accounting\Models\Tax;

class GetPurchaseTaxes
{
    /**
     * Return active taxes for use in purchase documents.
     *
     * Public cross-module API: exposes only the fields a purchasing
     * document needs (id, name, code, rate) and never leaks the
     * accounting account configuration.
     *
     * @return list<array{id: int, name: string, code: string, rate: float}>
     */
    public function execute(): array
    {
        return array_values(Tax::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Tax $tax): array => [
                'id' => $tax->id,
                'name' => $tax->name,
                'code' => $tax->code,
                'rate' => (float) $tax->rate,
            ])
            ->all());
    }
}
