<?php

namespace Modules\Approval\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

class UpdateApprovalRuleRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'currency_code' => ['sometimes', 'string', 'size:3'],
            'scope_all_users' => ['sometimes', 'boolean'],
            'scoped_user_ids' => ['required_if:scope_all_users,false', 'array'],
            'scoped_user_ids.*' => ['integer'],
            'apply_to_existing_draft' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'min_amount' => ['sometimes', 'numeric', 'gt:0', $this->integerWhenQuantityBasis()],
            'stages' => ['sometimes', 'array', 'min:1', 'max:2'],
            'stages.*.approval_type' => ['sometimes', 'in:any,all'],
            'stages.*.approver_ids' => ['sometimes', 'array', 'min:1'],
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
            $basis = DB::table('approval_rules as r')
                ->join('approval_transaction_types as t', 't.id', '=', 'r.transaction_type_id')
                ->where('r.id', (int) $this->route('rule'))
                ->value('t.criteria_basis');

            if ($basis === 'quantity' && ((int) $value < 1 || (float) $value !== (float) (int) $value)) {
                $fail('Untuk tipe transaksi basis qty, nilai threshold harus bilangan bulat minimal 1.');
            }
        };
    }
}
