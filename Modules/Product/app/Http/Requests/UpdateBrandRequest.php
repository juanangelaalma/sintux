<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $brandId = $this->route('brand');

        return [
            'name' => ['required', 'string', 'max:255', 'unique:brands,name,'.$brandId],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
