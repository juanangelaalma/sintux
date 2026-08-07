<?php

namespace Modules\Company\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->role === 'superadmin';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', 'unique:tenants,id'],
            'name' => ['required', 'string', 'max:255'],
            'schema_name' => ['required', 'string', 'alpha_dash', 'max:50', 'unique:tenants,schema_name'],
            'is_active' => ['boolean'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['required', 'string', 'min:8'],
        ];
    }
}
