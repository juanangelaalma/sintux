<?php

namespace Modules\Accounting\Application;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\AccountType;
use Modules\Accounting\Models\ChartOfAccount;

class CreateChartOfAccount
{
    public function __construct(private CanBecomeChartOfAccountParent $canBecomeChartOfAccountParent) {}

    /**
     * Create an account in the current tenant database.
     *
     * @param  array<string, mixed>  $data
     */
    public function execute(array $data): ChartOfAccount
    {
        try {
            return (new AccountType)->getConnection()->transaction(
                function () use ($data): ChartOfAccount {
                    $categoryId = (int) $data['account_category_id'];
                    $parentId = $data['parent_id'] ? (int) $data['parent_id'] : null;
                    $headerAccountIds = array_map('intval', $data['header_account_ids'] ?? []);
                    $detailType = $data['detail_type'];
                    $relatedAccountIds = $headerAccountIds;

                    if ($parentId) {
                        $relatedAccountIds[] = $parentId;
                    }

                    $relatedAccounts = ChartOfAccount::query()
                        ->whereIn('id', $relatedAccountIds)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($relatedAccounts->count() !== count(array_unique($relatedAccountIds))) {
                        throw ValidationException::withMessages([
                            'detail_type' => 'Pilihan detail akun sudah berubah. Silakan pilih kembali.',
                        ]);
                    }

                    if ($relatedAccounts->contains(
                        fn (ChartOfAccount $related): bool => $related->account_category_id !== $categoryId,
                    )) {
                        throw ValidationException::withMessages([
                            $detailType === 'sub_account' ? 'parent_id' : 'header_account_ids' => 'Akun yang dipilih harus berada dalam kategori yang sama.',
                        ]);
                    }

                    if ($detailType === 'sub_account') {
                        $parent = $relatedAccounts->firstWhere('id', $parentId);

                        if (! $parent || ! $this->canBecomeChartOfAccountParent->execute($parent)) {
                            throw ValidationException::withMessages([
                                'parent_id' => 'Akun induk tidak dapat digunakan.',
                            ]);
                        }

                        $parent->update(['is_header' => true]);
                    }

                    unset($data['detail_type'], $data['header_account_ids']);

                    $account = ChartOfAccount::query()->create($data);

                    if ($account->is_header) {
                        ChartOfAccount::query()
                            ->whereIn('id', $headerAccountIds)
                            ->update(['parent_id' => $account->id]);
                    }

                    return $account->refresh();
                },
            );
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'code' => 'Kode akun sudah digunakan. Pilih kode lain atau minta rekomendasi terbaru.',
            ]);
        }
    }
}
