<?php

namespace Modules\Company\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCompanyUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->hasPermissionTo('company.user.manage');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'company_role' => ['sometimes', 'in:admin,member'],
            'scope' => ['sometimes', 'in:all,branch'],
            'branch_id' => ['sometimes', 'nullable', 'integer', 'required_if:scope,branch'],
            'roles' => ['nullable', 'array'],
            'roles.*' => ['integer', 'exists:roles,id'],
        ];
    }
}
