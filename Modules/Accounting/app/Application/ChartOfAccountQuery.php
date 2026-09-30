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
     * Akun yang boleh dipilih sebagai akun biaya pada modul Expense.
     *
     * Batasannya account type, bukan daftar kategori: semua akun non-header
     * bertipe EXPENSE atau COST_OF_REVENUE. Akun baru yang dibuat lewat
     * CoA otomatis masuk tanpa perubahan kode. Lihat docs/expense/OPEN_QUESTIONS.md
     * Q-15 untuk alasannya.
     *
     * @return list<array{id: int, code: string, name: string}>
     */
    public function listForExpense(): array
    {
        return $this->listByAccountType(['EXPENSE', 'COST_OF_REVENUE']);
    }

    /**
     * Akun kas dan bank untuk kolom "Bayar dari" dan pelunasan Expense.
     *
     * @return list<array{id: int, code: string, name: string}>
     */
    public function listCashAndBank(): array
    {
        return ChartOfAccount::query()
            ->join('coa_account_categories', 'coa_account_categories.id', '=', 'chart_of_accounts.account_category_id')
            ->where('chart_of_accounts.is_header', false)
            ->whereNull('chart_of_accounts.deleted_at')
            ->whereIn('coa_account_categories.code', ['CASH_AND_BANK'])
            ->orderBy('chart_of_accounts.code')
            ->get(['chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name'])
            ->map(fn (ChartOfAccount $account): array => [
                'id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $accountTypeCodes
     * @return list<array{id: int, code: string, name: string}>
     */
    private function listByAccountType(array $accountTypeCodes): array
    {
        return ChartOfAccount::query()
            ->join('coa_account_categories', 'coa_account_categories.id', '=', 'chart_of_accounts.account_category_id')
            ->join('coa_account_types', 'coa_account_types.id', '=', 'coa_account_categories.account_type_id')
            ->where('chart_of_accounts.is_header', false)
            ->whereNull('chart_of_accounts.deleted_at')
            ->whereIn('coa_account_types.code', $accountTypeCodes)
            ->orderBy('chart_of_accounts.code')
            ->get(['chart_of_accounts.id', 'chart_of_accounts.code', 'chart_of_accounts.name'])
            ->map(fn (ChartOfAccount $account): array => [
                'id' => (int) $account->id,
                'code' => (string) $account->code,
                'name' => (string) $account->name,
            ])
            ->values()
            ->all();
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
