<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Accounting\Models\AccountCategory;
use Modules\Accounting\Models\ChartOfAccount;

class StoreChartOfAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('accounting.account.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_category_id' => ['bail', 'required', 'integer', 'exists:coa_account_categories,id'],
            'detail_type' => ['required', Rule::in(['none', 'sub_account', 'header'])],
            'parent_id' => [
                'bail',
                'nullable',
                'required_if:detail_type,sub_account',
                'prohibited_unless:detail_type,sub_account',
                'integer',
                Rule::exists('chart_of_accounts', 'id')->whereNull('deleted_at')->where('is_header', true),
            ],
            'header_account_ids' => [
                'exclude_unless:detail_type,header',
                'nullable',
                'required_if:detail_type,header',
                'array',
                'min:1',
            ],
            'header_account_ids.*' => [
                'bail',
                'integer',
                'distinct',
                Rule::exists('chart_of_accounts', 'id')->whereNull('deleted_at'),
            ],
            'default_tax_id' => [
                'bail',
                'nullable',
                'prohibited_if:detail_type,header',
                'integer',
                Rule::exists('taxes', 'id')->where('is_active', true),
            ],
            'code' => ['bail', 'required', 'string', 'max:50', 'unique:chart_of_accounts,code'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string'],
            'is_header' => ['required', 'boolean'],
        ];
    }

    /**
     * Derive persisted hierarchy fields from the selected detail option.
     */
    protected function prepareForValidation(): void
    {
        $detailType = $this->input('detail_type', 'none');

        $this->merge([
            'parent_id' => $detailType === 'sub_account' ? $this->input('parent_id') : null,
            'header_account_ids' => $detailType === 'header' ? $this->input('header_account_ids', []) : [],
            'default_tax_id' => $detailType === 'header' ? null : $this->input('default_tax_id'),
            'is_header' => $detailType === 'header',
        ]);
    }

    /**
     * Validate category-specific code format and parent ownership.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny([
                'account_category_id',
                'detail_type',
                'parent_id',
                'header_account_ids',
                'header_account_ids.*',
                'code',
            ])) {
                return;
            }

            $category = AccountCategory::query()->with('accountType')->find($this->integer('account_category_id'));

            if (! $category?->prefix || ! $category->accountType->prefix) {
                $validator->errors()->add('account_category_id', 'Prefix tipe dan kategori akun belum dikonfigurasi.');

                return;
            }

            $baseCode = $category->accountType->prefix.'-'.$category->prefix;

            if (preg_match('/^'.preg_quote($baseCode, '/').'\d{2,}$/', $this->string('code')->toString()) !== 1) {
                $validator->errors()->add('code', "Kode akun harus menggunakan format {$baseCode}XX.");
            }

            if ($this->filled('parent_id')) {
                $parent = ChartOfAccount::query()->find($this->integer('parent_id'));

                if ($parent && $parent->account_category_id !== $category->id) {
                    $validator->errors()->add('parent_id', 'Akun induk harus berada dalam kategori yang sama.');
                }
            }

            if ($this->input('detail_type') === 'header') {
                $hasDifferentCategory = ChartOfAccount::query()
                    ->whereIn('id', $this->input('header_account_ids', []))
                    ->where('account_category_id', '!=', $category->id)
                    ->exists();

                if ($hasDifferentCategory) {
                    $validator->errors()->add(
                        'header_account_ids',
                        'Semua akun yang dipilih harus berada dalam kategori yang sama.',
                    );
                }
            }
        }];
    }
}
