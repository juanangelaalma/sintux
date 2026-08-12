<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Accounting\Models\ChartOfAccount;

class UpdateChartOfAccountRequest extends StoreChartOfAccountRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $currentTaxId = ChartOfAccount::query()
            ->whereKey($this->accountId())
            ->value('default_tax_id');

        $rules['code'] = [
            'bail',
            'required',
            'string',
            'max:50',
            Rule::unique('chart_of_accounts', 'code')->ignore($this->accountId()),
        ];
        $rules['default_tax_id'] = [
            'bail',
            'nullable',
            'prohibited_if:detail_type,header',
            'integer',
            Rule::exists('taxes', 'id')->where(
                fn ($query) => $query
                    ->where('is_active', true)
                    ->when($currentTaxId, fn ($query) => $query->orWhere('id', $currentTaxId)),
            ),
        ];

        return $rules;
    }

    /**
     * Protect the existing account hierarchy while editing.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            ...parent::after(),
            function (Validator $validator): void {
                if ($validator->errors()->hasAny([
                    'account_category_id',
                    'detail_type',
                    'parent_id',
                    'header_account_ids',
                    'header_account_ids.*',
                    'is_header',
                ])) {
                    return;
                }

                $account = ChartOfAccount::query()->find($this->accountId());

                if (! $account) {
                    return;
                }

                if ($account->children()->withTrashed()->exists()) {
                    if (! $this->boolean('is_header')) {
                        $validator->errors()->add('is_header', 'Akun yang memiliki turunan harus tetap menjadi akun header.');
                    }

                    if ($account->account_category_id !== $this->integer('account_category_id')) {
                        $validator->errors()->add('account_category_id', 'Kategori akun header yang memiliki turunan tidak dapat diubah.');
                    }
                }

                if ($this->filled('parent_id') && $this->wouldCreateCycle($account)) {
                    $validator->errors()->add('parent_id', 'Akun induk tidak boleh berupa akun itu sendiri atau turunannya.');
                }

                if ($this->input('detail_type') === 'header') {
                    $selectedIds = array_map('intval', $this->input('header_account_ids', []));

                    if (in_array($account->id, $selectedIds, true) || $this->containsAncestor($account, $selectedIds)) {
                        $validator->errors()->add(
                            'header_account_ids',
                            'Akun header tidak boleh menjadi turunan dari dirinya sendiri.',
                        );
                    }

                    $parentCategoryId = ChartOfAccount::withTrashed()
                        ->whereKey($account->parent_id)
                        ->value('account_category_id');

                    if ($parentCategoryId && $parentCategoryId !== $this->integer('account_category_id')) {
                        $validator->errors()->add(
                            'account_category_id',
                            'Kategori akun harus sama dengan akun induknya.',
                        );
                    }
                }
            },
        ];
    }

    private function accountId(): int
    {
        return (int) $this->route('chartOfAccount');
    }

    private function wouldCreateCycle(ChartOfAccount $account): bool
    {
        $parentId = $this->integer('parent_id');
        $visited = [];

        while ($parentId && ! isset($visited[$parentId])) {
            if ($parentId === $account->id) {
                return true;
            }

            $visited[$parentId] = true;
            $parentId = (int) ChartOfAccount::withTrashed()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }

    /**
     * @param  list<int>  $selectedIds
     */
    private function containsAncestor(ChartOfAccount $account, array $selectedIds): bool
    {
        $parentId = $account->parent_id;
        $visited = [];

        while ($parentId && ! isset($visited[$parentId])) {
            if (in_array($parentId, $selectedIds, true)) {
                return true;
            }

            $visited[$parentId] = true;
            $parentId = ChartOfAccount::withTrashed()->whereKey($parentId)->value('parent_id');
        }

        return false;
    }
}
