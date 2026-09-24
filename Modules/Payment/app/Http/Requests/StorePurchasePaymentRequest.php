<?php

namespace Modules\Payment\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchasePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'branch_id' => ['required', 'integer'],
            'supplier_id' => ['required', 'integer'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'cash_account_id' => ['required', 'integer'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['nullable', 'date'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'memo' => ['nullable', 'string', 'max:1000'],
            'mode' => ['nullable', 'in:invoice,deposit'],
            'amount' => ['required_if:mode,deposit', 'nullable', 'numeric', 'gt:0'],

            'allocations' => ['required_if:mode,invoice', 'nullable', 'array', 'min:1'],
            'allocations.*.purchase_invoice_id' => ['required', 'integer'],
            'allocations.*.amount' => ['required', 'numeric', 'gt:0'],

            'withholdings' => ['nullable', 'array'],
            'withholdings.*.account_id' => ['required', 'integer'],
            'withholdings.*.type' => ['required', 'in:percent,nominal'],
            'withholdings.*.value' => ['required', 'numeric', 'gt:0'],

            'deposit_uses' => ['nullable', 'array'],
            'deposit_uses.*.payment_id' => ['required', 'integer'],
            'deposit_uses.*.amount' => ['required', 'numeric', 'gt:0'],

            'memo_uses' => ['nullable', 'array'],
            'memo_uses.*.memo_id' => ['required', 'integer'],
            'memo_uses.*.amount' => ['required', 'numeric', 'gt:0'],

            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer'],
        ];
    }
}
