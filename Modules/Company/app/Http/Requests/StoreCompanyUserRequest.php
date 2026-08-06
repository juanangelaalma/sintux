<?php

namespace Modules\Company\Http\Requests;

use App\Models\Role;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (User::query()->where('email', $value)->exists()) {
                        $fail('The email has already been taken.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8'],
            'company_role' => ['required', 'in:admin,member'],
            'scope' => ['required', 'in:all,branch'],
            'branch_id' => ['nullable', 'integer', 'required_if:scope,branch'],
            'roles' => ['nullable', 'array'],
            'roles.*' => [
                'integer',
                static function (string $attribute, mixed $value, Closure $fail): void {
                    if (! Role::query()->whereKey($value)->exists()) {
                        $fail('The selected role is invalid.');
                    }
                },
            ],
        ];
    }
}
