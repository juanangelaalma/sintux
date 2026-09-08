<?php

namespace Modules\Accounting\Application;

use Modules\Accounting\Models\Tax;

final class TaxQuery
{
    /**
     * @return list<array{id: int, code: string, name: string, rate: string}>
     */
    public function listForPurchase(): array
    {
        return $this->listEligible('input_account_id');
    }

    /**
     * @return list<array{id: int, code: string, name: string, rate: string}>
     */
    public function listForSale(): array
    {
        return $this->listEligible('output_account_id');
    }

    public function isEligibleForPurchase(int $taxId): bool
    {
        return $this->isEligible($taxId, 'input_account_id');
    }

    public function isEligibleForSale(int $taxId): bool
    {
        return $this->isEligible($taxId, 'output_account_id');
    }

    /**
     * @return list<array{id: int, code: string, name: string, rate: string}>
     */
    private function listEligible(string $accountColumn): array
    {
        return Tax::query()
            ->select('id', 'code', 'name', 'rate')
            ->where('is_active', true)
            ->whereNotNull($accountColumn)
            ->orderBy('code')
            ->get()
            ->map(fn (Tax $tax): array => [
                'id' => $tax->id,
                'code' => $tax->code,
                'name' => $tax->name,
                'rate' => $tax->rate,
            ])
            ->all();
    }

    private function isEligible(int $taxId, string $accountColumn): bool
    {
        return Tax::query()
            ->whereKey($taxId)
            ->where('is_active', true)
            ->whereNotNull($accountColumn)
            ->exists();
    }
}
