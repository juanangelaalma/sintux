<?php

namespace Modules\Accounting\Application;

use Illuminate\Database\Eloquent\Collection;
use Modules\Accounting\Models\AccountCategory;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Tax;

class GetChartOfAccounts
{
    /**
     * Return page data for the tenant's chart of accounts.
     *
     * @return array{
     *     accounts: list<array<string, mixed>>,
     *     categories: list<array<string, mixed>>,
     *     parentAccounts: list<array<string, mixed>>,
     *     taxes: list<array<string, mixed>>
     * }
     */
    public function execute(): array
    {
        $accounts = ChartOfAccount::withTrashed()
            ->with('category.accountType')
            ->orderBy('code')
            ->get();

        $categories = array_values(AccountCategory::query()
            ->with('accountType')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (AccountCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'prefix' => $category->prefix,
                'type_name' => $category->accountType->name,
                'type_prefix' => $category->accountType->prefix,
            ])
            ->values()
            ->all());

        $parentAccounts = array_values($accounts
            ->whereNull('deleted_at')
            ->where('is_header', true)
            ->map(fn (ChartOfAccount $account): array => [
                'id' => $account->id,
                'account_category_id' => $account->account_category_id,
                'code' => $account->code,
                'name' => $account->name,
            ])
            ->values()
            ->all());

        $referencedTaxIds = $accounts
            ->pluck('default_tax_id')
            ->filter()
            ->unique()
            ->values();

        $taxes = Tax::query()
            ->where(function ($query) use ($referencedTaxIds): void {
                $query->where('is_active', true)
                    ->orWhereIn('id', $referencedTaxIds);
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Tax $tax): array => [
                'id' => $tax->id,
                'name' => $tax->name,
                'code' => $tax->code,
                'rate' => (float) $tax->rate,
                'is_active' => $tax->is_active,
            ])
            ->values()
            ->all();

        return [
            'accounts' => $this->buildTree($accounts),
            'categories' => $categories,
            'parentAccounts' => $parentAccounts,
            'taxes' => array_values($taxes),
        ];
    }

    /**
     * @param  Collection<int, ChartOfAccount>  $accounts
     * @return list<array<string, mixed>>
     */
    private function buildTree(Collection $accounts, ?int $parentId = null): array
    {
        $tree = [];

        foreach ($accounts as $account) {
            if ($account->parent_id !== $parentId) {
                continue;
            }

            $tree[] = [
                'id' => $account->id,
                'parent_id' => $account->parent_id,
                'account_category_id' => $account->account_category_id,
                'default_tax_id' => $account->default_tax_id,
                'code' => $account->code,
                'name' => $account->name,
                'description' => $account->description,
                'is_header' => $account->is_header,
                'is_archived' => $account->trashed(),
                'category_name' => $account->category->name,
                'type_name' => $account->category->accountType->name,
                'normal_balance' => $account->category->accountType->normal_balance,
                'children' => $this->buildTree($accounts, $account->id),
            ];
        }

        return $tree;
    }
}
