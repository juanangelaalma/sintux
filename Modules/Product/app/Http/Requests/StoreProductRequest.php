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
            'barcode' => ['nullable', 'string', 'max:100'],
            'category_id' => ['required', 'integer', 'exists:product_categories,id'],
            'uom_id' => ['required', 'integer', 'exists:uoms,id'],
            'description' => ['nullable', 'string', 'max:6000'],
            'image_path' => ['nullable', 'string'],
            'product_type' => ['sometimes', 'string', Rule::in(['single', 'bundle'])],

            // Purchase
            'is_purchased' => ['sometimes', 'boolean'],
            'purchase_price' => ['sometimes', 'numeric', 'min:0'],
            'purchase_account_id' => ['nullable', 'integer'],
            'purchase_tax_id' => ['nullable', 'integer'],

            // Sales
            'is_sold' => ['sometimes', 'boolean'],
            'selling_price' => ['sometimes', 'numeric', 'min:0'],
            'sales_account_id' => ['nullable', 'integer'],
            'sales_tax_id' => ['nullable', 'integer'],

            // Inventory
            'is_inventory_tracked' => ['sometimes', 'boolean'],
            'min_stock' => ['sometimes', 'numeric', 'min:0'],
            'inventory_account_id' => ['nullable', 'integer'],

            'is_active' => ['sometimes', 'boolean'],

            // Bundle items
            'bundle_items' => ['nullable', 'array'],
            'bundle_items.*.item_product_id' => ['required_with:bundle_items', 'integer', 'exists:products,id'],
            'bundle_items.*.quantity' => ['required_with:bundle_items', 'numeric', 'gt:0'],
        ];
    }
}
