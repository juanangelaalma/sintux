<?php

namespace Modules\Accounting\Application;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\ChartOfAccount;

class UpdateChartOfAccount
{
    public function __construct(private CanBecomeChartOfAccountParent $canBecomeChartOfAccountParent) {}

    /**
     * Update an account in the current tenant database.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(ChartOfAccount $account, array $data): ChartOfAccount
    {
        try {
            return $account->getConnection()->transaction(function () use ($account, $data): ChartOfAccount {
                $categoryId = (int) $data['account_category_id'];
                $parentId = $data['parent_id'] ? (int) $data['parent_id'] : null;
                $headerAccountIds = array_map('intval', $data['header_account_ids'] ?? []);
                $detailType = $data['detail_type'];
                $relatedAccountIds = $headerAccountIds;

                if ($parentId) {
                    $relatedAccountIds[] = $parentId;
                }

                $lockedAccounts = ChartOfAccount::withTrashed()
                    ->where(function ($query) use ($account, $categoryId, $relatedAccountIds): void {
                        $query->whereIn('account_category_id', array_unique([
                            $account->account_category_id,
                            $categoryId,
                        ]))->orWhereIn('id', $relatedAccountIds);
                    })
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $account = $lockedAccounts->firstWhere('id', $account->id);

                if (! $account || $account->trashed()) {
                    throw ValidationException::withMessages([
                        'detail_type' => 'Akun sudah berubah atau tidak lagi tersedia.',
                    ]);
                }

                $this->validateLockedHierarchy(
                    $account,
                    $lockedAccounts,
                    $detailType,
                    $categoryId,
                    $parentId,
                    $headerAccountIds,
                );

                $hasChildren = $lockedAccounts->contains(
                    fn (ChartOfAccount $candidate): bool => $candidate->parent_id === $account->id,
                );

                if ($detailType === 'sub_account') {
                    $lockedAccounts->firstWhere('id', $parentId)->update(['is_header' => true]);
                }

                unset($data['detail_type'], $data['header_account_ids']);

                if ($hasChildren) {
                    $data['is_header'] = true;
                }

                if ($detailType === 'header' && $account->is_header && $data['is_header']) {
                    $data['parent_id'] = $account->parent_id;
                }

                $account->fill($data)->save();

                if ($account->is_header && $detailType === 'header') {
                    $account->children()
                        ->whereNotIn('id', $headerAccountIds)
                        ->update(['parent_id' => null]);

                    ChartOfAccount::query()
                        ->whereIn('id', $headerAccountIds)
                        ->update(['parent_id' => $account->id]);
                }

                return $account->refresh();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => 'Kode akun sudah digunakan. Pilih kode lain atau minta rekomendasi terbaru.',
            ]);
        }
    }

    /**
     * Recheck hierarchy invariants after all affected rows have been locked.
     *
     * @param  Collection<int, ChartOfAccount>  $accounts
     * @param  list<int>  $headerAccountIds
     */
    private function validateLockedHierarchy(
        ChartOfAccount $account,
        Collection $accounts,
        string $detailType,
        int $categoryId,
        ?int $parentId,
        array $headerAccountIds,
    ): void {
        $selectedIds = $detailType === 'header' ? $headerAccountIds : ($parentId ? [$parentId] : []);
        $selectedAccounts = $accounts
            ->whereIn('id', $selectedIds)
            ->filter(fn (ChartOfAccount $selected): bool => ! $selected->trashed());

        if ($selectedAccounts->count() !== count(array_unique($selectedIds))) {
            throw ValidationException::withMessages([
                'detail_type' => 'Pilihan detail akun sudah berubah. Silakan pilih kembali.',
            ]);
        }

        if ($selectedAccounts->contains(
            fn (ChartOfAccount $selected): bool => $selected->account_category_id !== $categoryId,
        )) {
            throw ValidationException::withMessages([
                $detailType === 'sub_account' ? 'parent_id' : 'header_account_ids' => 'Akun yang dipilih harus berada dalam kategori yang sama.',
            ]);
        }

        if ($detailType === 'sub_account') {
            $parent = $selectedAccounts->firstWhere('id', $parentId);

            if (! $parent
                || ! $this->canBecomeChartOfAccountParent->execute($parent)
                || $parent->id === $account->id
                || $this->isDescendantOf($parent, $account->id, $accounts)) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Akun induk tidak valid atau akan membuat siklus hierarki.',
                ]);
            }
        }

        $hasChildren = $accounts->contains(
            fn (ChartOfAccount $candidate): bool => $candidate->parent_id === $account->id,
        );

        if ($hasChildren && $account->account_category_id !== $categoryId) {
            throw ValidationException::withMessages([
                'account_category_id' => 'Kategori akun header yang memiliki turunan tidak dapat diubah.',
            ]);
        }

        if ($detailType === 'header') {
            $ancestorIds = [];
            $ancestorId = $account->parent_id;

            while ($ancestorId && ! in_array($ancestorId, $ancestorIds, true)) {
                $ancestorIds[] = $ancestorId;
                $ancestorId = $accounts->firstWhere('id', $ancestorId)?->parent_id;
            }

            if (in_array($account->id, $headerAccountIds, true) || array_intersect($ancestorIds, $headerAccountIds)) {
                throw ValidationException::withMessages([
                    'header_account_ids' => 'Akun header tidak boleh menjadi turunan dari dirinya sendiri.',
                ]);
            }
        }
    }

    /**
     * @param  Collection<int, ChartOfAccount>  $accounts
     */
    private function isDescendantOf(ChartOfAccount $candidate, int $accountId, Collection $accounts): bool
    {
        $parentId = $candidate->parent_id;
        $visited = [];

        while ($parentId && ! in_array($parentId, $visited, true)) {
            if ($parentId === $accountId) {
                return true;
            }

            $visited[] = $parentId;
            $parentId = $accounts->firstWhere('id', $parentId)?->parent_id;
        }

        return false;
    }
}
