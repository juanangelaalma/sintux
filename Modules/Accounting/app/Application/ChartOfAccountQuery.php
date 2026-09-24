<?php

namespace Modules\Accounting\Application;

use Modules\Accounting\Models\ChartOfAccount;

class ChartOfAccountQuery
{
    /**
     * Return list of all chart of accounts.
     *
     * @return list<array<string, mixed>>
     */
    public function listChartOfAccounts(): array
    {
        return ChartOfAccount::select('id', 'code', 'name')
            ->where('is_header', false)
            ->get()
            ->map(fn (ChartOfAccount $account): array => [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
            ])
            ->values()
            ->all();
    }

    public function isEligible(int $accountId): bool
    {
        return ChartOfAccount::query()
            ->whereKey($accountId)
            ->where('is_header', false)
            ->exists();
    }

    /**
     * Cari akun non-header yang hidup via seed_key (identitas stabil
     * antar tenant, mis. accounting.coa.1301). Proyeksi saja.
     *
     * @return array{id: int, code: string, name: string}|null
     */
    public function findBySeedKey(string $seedKey): ?array
    {
        $account = ChartOfAccount::query()
            ->where('seed_key', $seedKey)
            ->where('is_header', false)
            ->first();

        if (! $account) {
            return null;
        }

        return [
            'id' => (int) $account->id,
            'code' => (string) $account->code,
            'name' => (string) $account->name,
        ];
    }
}
