<?php

namespace Modules\Contact\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Allowed for all company members for now
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'shipping_same_as_billing' => ['sometimes', 'boolean'],
            'billing_address' => ['nullable', 'array'],
            'shipping_address' => ['nullable', 'array'],
            ...$this->addressRules('billing_address'),
            ...$this->addressRules('shipping_address'),
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function addressRules(string $prefix): array
    {
        return [
            "{$prefix}.detail" => ['nullable', 'string', 'max:255'],
            "{$prefix}.rt" => ['nullable', 'string', 'max:10'],
            "{$prefix}.rw" => ['nullable', 'string', 'max:10'],
            "{$prefix}.kelurahan" => ['nullable', 'string', 'max:255'],
            "{$prefix}.kecamatan" => ['nullable', 'string', 'max:255'],
            "{$prefix}.kabupaten" => ['nullable', 'string', 'max:255'],
            "{$prefix}.provinsi" => ['nullable', 'string', 'max:255'],
            "{$prefix}.latitude" => ['nullable', 'numeric', 'between:-90,90'],
            "{$prefix}.longitude" => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
