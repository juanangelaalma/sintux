<?php

namespace Modules\Product\Application\Variant;

use Modules\Product\Models\ProductVariant;

class FindVariantBySkuAndColor
{
    /**
     * Normalisasi nama warna agar cocok dengan format supplier yang
     * tidak konsisten (spasi ganda, huruf kecil/besar).
     */
    public static function normalizeColor(?string $color): string
    {
        if ($color === null) {
            return '';
        }

        $collapsed = (string) preg_replace('/\s+/', ' ', trim($color));

        return mb_strtoupper($collapsed);
    }

    /**
     * Normalisasi atribut varian sebelum tulis agar identitas
     * (branch, sku, warna) konsisten antara validasi, pencarian,
     * dan unique index di database.
     */
    public static function normalizeAttributes(mixed $attributes): mixed
    {
        if (! is_array($attributes)) {
            return $attributes;
        }

        if (array_key_exists('color', $attributes)) {
            $attributes['color'] = self::normalizeColor(
                is_string($attributes['color']) ? $attributes['color'] : null
            );
        }

        return $attributes;
    }

    public function execute(string $sku, ?string $color, ?int $branchId = null): ?ProductVariant
    {
        $normalized = self::normalizeColor($color);

        $query = ProductVariant::query()
            ->where('sku', $sku)
            ->whereRaw("UPPER(COALESCE(attributes->>'color', '')) = ?", [$normalized])
            ->whereNull('deleted_at');

        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        return $query->first();
    }
}
