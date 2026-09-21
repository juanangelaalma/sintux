<?php

namespace Modules\Product\Application\Variant;

use Modules\Product\Models\ProductVariant;

/**
 * Ringkasan varian untuk konsumsi lintas modul.
 *
 * Mengembalikan array datar (bukan model) agar konsumen tidak bergantung
 * pada relasi internal Product dan tidak memicu lazy-load lintas batas.
 *
 * @return array{id: int, uom_name: string|null}|null
 */
class FindVariantSummary
{
    public function execute(string $sku, ?string $color, int $branchId): ?array
    {
        $normalized = FindVariantBySkuAndColor::normalizeColor($color);

        $row = ProductVariant::query()
            ->where('product_variants.branch_id', $branchId)
            ->where('product_variants.sku', $sku)
            ->whereRaw("UPPER(COALESCE(product_variants.attributes->>'color', '')) = ?", [$normalized])
            ->leftJoin('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('uoms', 'uoms.id', '=', 'products.uom_id')
            ->first([
                'product_variants.id as id',
                'uoms.name as uom_name',
            ]);

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'uom_name' => $row->uom_name !== null && $row->uom_name !== ''
                ? (string) $row->uom_name
                : null,
        ];
    }
}
