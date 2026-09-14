<?php

namespace Modules\Approval\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class StoreApprovalRuleRequest extends FormRequest
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
            'transaction_type_id' => ['required', 'integer', 'exists:approval_transaction_types,id'],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'scope_all_users' => ['sometimes', 'boolean'],
            'scoped_user_ids' => ['required_if:scope_all_users,false', 'array'],
            'scoped_user_ids.*' => ['integer'],
            'apply_to_existing_draft' => ['sometimes', 'boolean'],
            'min_amount' => ['required', 'numeric', 'gt:0', $this->integerWhenQuantityBasis()],
            'stages' => ['required', 'array', 'min:1', 'max:2'],
            'stages.*.approval_type' => ['required', 'in:any,all'],
            'stages.*.approver_ids' => ['required', 'array', 'min:1'],
            'stages.*.approver_ids.*' => ['integer'],
        ];
    }

    /**
     * Threshold basis quantity (mis. total qty transfer stok)
     * harus bilangan bulat minimal 1.
     */
    private function integerWhenQuantityBasis(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $basis = DB::table('approval_transaction_types')
                ->where('id', (int) $this->input('transaction_type_id'))
                ->value('criteria_basis');

            if ($basis === 'quantity' && ((int) $value < 1 || (float) $value !== (float) (int) $value)) {
                $fail('Untuk tipe transaksi basis qty, nilai threshold harus bilangan bulat minimal 1.');
            }
        };
    }
}
