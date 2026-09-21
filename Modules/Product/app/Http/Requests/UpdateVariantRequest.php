<?php

namespace Modules\Product\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Modules\Product\Application\Variant\FindVariantBySkuAndColor;

class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $variantId = $this->route('variant');

        // Identitas varian = (branch, sku, warna), scoped ke cabang varian.
        $compositeUnique = function (string $attribute, mixed $value, \Closure $fail) use ($variantId) {
            $current = DB::table('product_variants')->where('id', $variantId)->first();

            if (! $current) {
                return;
            }

            $currentAttributes = is_string($current->attributes)
                ? (json_decode($current->attributes, true) ?? [])
                : ((array) ($current->attributes ?? []));

            // Warna yang diperiksa = input bila key-nya dikirim (termasuk
            // null eksplisit = hapus warna), selain itu warna tersimpan.
            // Payload attributes tanpa key color TIDAK boleh mengosongkan
            // warna saat cek duplikat.
            $attributesInput = $this->input('attributes');
            $rawColor = is_array($attributesInput) && array_key_exists('color', $attributesInput)
                ? $attributesInput['color']
                : ($currentAttributes['color'] ?? null);
            $color = FindVariantBySkuAndColor::normalizeColor(
                is_string($rawColor) ? $rawColor : null
            );

            $exists = DB::table('product_variants')
                ->where('branch_id', $current->branch_id)
                ->where('sku', (string) $value)
                ->whereRaw("UPPER(COALESCE(attributes->>'color', '')) = ?", [$color])
                ->whereNull('deleted_at')
                ->where('id', '!=', $variantId)
                ->exists();

            if ($exists) {
                $fail('Kombinasi SKU dan warna sudah ada di cabang ini.');
            }
        };

        return [
            'product_id' => ['sometimes', 'integer', 'exists:products,id'],
            'sku' => ['required', 'string', 'max:50', $compositeUnique],
            'variant_name' => ['required', 'string', 'max:255'],
            'attributes' => ['nullable', 'array'],
            'attributes.color' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
