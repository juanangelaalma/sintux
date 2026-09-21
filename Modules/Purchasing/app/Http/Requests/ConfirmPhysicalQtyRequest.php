<?php

namespace Modules\Purchasing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmPhysicalQtyRequest extends FormRequest
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
            'confirmed' => ['required', 'boolean'],
        ];
    }
}
