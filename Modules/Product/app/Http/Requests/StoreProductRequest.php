<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('products', 'code')->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
