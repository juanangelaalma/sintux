<?php

namespace Modules\Product\Application\Variant;

use Modules\Product\Models\ProductVariant;

class GetVariantForBranch
{
    public function __construct(
        private readonly FindVariantBySkuAndColor $findBySkuAndColor,
    ) {}

    /**
     * Varian yang dipakai cabang gudang untuk stok yang berasal dari
     * varian lain (mis. layer hasil receive transfer).
     *
     * Public cross-module API (read-only, tanpa create): id sama-cabang
     * kembali apa adanya; beda cabang dicari padanan SKU+warna
     * (cermin hasil receive); null bila tak ada — pemanggil memblokir.
     *
     * @return array{variant_id: int}|null
     */
    public function execute(int $variantId, int $branchId): ?array
    {
        $variant = ProductVariant::find($variantId);

        if (! $variant) {
            return null;
        }

        if ((int) $variant->branch_id === $branchId) {
            return ['variant_id' => (int) $variant->id];
        }

        $mirror = $this->findBySkuAndColor->execute(
            (string) $variant->sku,
            $variant->attributes['color'] ?? null,
            $branchId
        );

        if (! $mirror) {
            return null;
        }

        return ['variant_id' => (int) $mirror->id];
    }
}
