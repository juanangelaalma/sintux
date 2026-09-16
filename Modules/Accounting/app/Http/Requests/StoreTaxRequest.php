<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Accounting\Models\Tax;

class StoreTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(ChartOfAccountQuery $accounts): array
    {
        return [
            'type' => ['required', Rule::in([Tax::TYPE_SINGLE, Tax::TYPE_GROUP])],
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:30', Rule::unique('taxes', 'code')],
            'rate' => ['nullable', 'required_if:type,single', 'numeric', 'min:0', 'max:100'],
            'is_withholding' => ['sometimes', 'boolean'],
            'dpp_multiplier' => ['sometimes', 'boolean'],
            'input_account_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) use ($accounts): void {
                if ($value && ! $accounts->isEligible((int) $value)) {
                    $fail('Akun pajak pembelian tidak valid.');
                }
            }],
            'output_account_id' => ['nullable', 'integer', function (string $attribute, mixed $value, \Closure $fail) use ($accounts): void {
                if ($value && ! $accounts->isEligible((int) $value)) {
                    $fail('Akun pajak penjualan tidak valid.');
                }
            }],
            'members' => ['required_if:type,group', 'array', 'min:1'],
            'members.*.id' => ['required', 'integer', 'exists:taxes,id'],
            'members.*.is_compound' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'members.required' => 'Grup pajak membutuhkan minimal satu pajak satuan.',
            'members.min' => 'Grup pajak membutuhkan minimal satu pajak satuan.',
            'rate.required_if' => 'Tarif pajak wajib diisi untuk pajak satuan.',
        ];
    }
}
