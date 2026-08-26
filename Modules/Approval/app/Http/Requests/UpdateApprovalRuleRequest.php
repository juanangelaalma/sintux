<?php

namespace Modules\Approval\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'min_amount' => ['sometimes', 'numeric', 'gt:0'],
            'stages' => ['sometimes', 'array', 'min:1', 'max:2'],
            'stages.*.approval_type' => ['sometimes', 'in:any,all'],
            'stages.*.approver_ids' => ['sometimes', 'array', 'min:1'],
            'stages.*.approver_ids.*' => ['integer'],
        ];
    }
}
