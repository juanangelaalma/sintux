<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Modules\Product\Application\Variant\FindVariantBySkuAndColor;

class StoreVariantRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if (! $this->filled('branch_id')) {
            $this->merge(['branch_id' => session('active_branch_id')]);
        }
    }

    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        // Identitas varian = (branch, sku, warna). SKU boleh sama untuk
        // warna berbeda; kombinasi yang sama ditolak.
        $compositeUnique = function (string $attribute, mixed $value, \Closure $fail) {
            $color = FindVariantBySkuAndColor::normalizeColor(
                is_string($this->input('attributes.color')) ? $this->input('attributes.color') : null
            );

            $exists = DB::table('product_variants')
                ->where('branch_id', $this->input('branch_id'))
                ->where('sku', (string) $value)
                ->whereRaw("UPPER(COALESCE(attributes->>'color', '')) = ?", [$color])
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                $fail('Kombinasi SKU dan warna sudah ada di cabang ini.');
            }
        };

        return [
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'sku' => ['required', 'string', 'max:50', $compositeUnique],
            'variant_name' => ['required', 'string', 'max:255'],
            'attributes' => ['nullable', 'array'],
            'attributes.color' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
