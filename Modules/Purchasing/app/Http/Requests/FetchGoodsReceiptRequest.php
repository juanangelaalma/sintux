<?php

namespace Modules\Purchasing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FetchGoodsReceiptRequest extends FormRequest
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
            'do_no' => ['required', 'string', 'max:60'],
            'purchase_order_id' => ['nullable', 'integer', 'exists:purchase_orders,id'],
            'customer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
