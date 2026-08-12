<?php

namespace Modules\Accounting\Application;

use Illuminate\Validation\ValidationException;
use Modules\Accounting\Models\AccountCategory;
use Modules\Accounting\Models\ChartOfAccount;

class GenerateChartOfAccountCode
{
    /**
     * Recommend the next available code for a category in the current tenant.
     */
    public function execute(AccountCategory $category): string
    {
        $category->loadMissing('accountType');
        $typePrefix = $category->accountType->prefix;
        $categoryPrefix = $category->prefix;

        if (! $typePrefix || ! $categoryPrefix) {
            throw ValidationException::withMessages([
                'account_category_id' => 'Prefix tipe dan kategori akun harus dikonfigurasi terlebih dahulu.',
            ]);
        }

        $baseCode = $typePrefix.'-'.$categoryPrefix;
        $pattern = '/^'.preg_quote($baseCode, '/').'(\d{2,})$/';
        $largestSequence = 0;

        $codes = ChartOfAccount::withTrashed()
            ->where('account_category_id', $category->id)
            ->where('code', 'like', $baseCode.'%')
            ->pluck('code');

        foreach ($codes as $code) {
            if (preg_match($pattern, $code, $matches) === 1) {
                $largestSequence = max($largestSequence, (int) $matches[1]);
            }
        }

        return $baseCode.str_pad((string) ($largestSequence + 1), 2, '0', STR_PAD_LEFT);
    }
}
