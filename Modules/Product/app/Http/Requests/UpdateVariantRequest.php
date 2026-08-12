<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $variantId = $this->route('variant');

        return [
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'sku' => ['required', 'string', 'max:50', Rule::unique('product_variants', 'sku')->whereNull('deleted_at')->ignore($variantId)],
            'variant_name' => ['required', 'string', 'max:255'],
            'attributes' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
