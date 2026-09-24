<?php

namespace Modules\Product\Application\Variant;

use Modules\Product\Models\ProductVariant;

class GetVariantReturnProfile
{
    /**
     * Profil varian untuk dokumen retur pembelian.
     *
     * Public cross-module API: hanya field yang dibutuhkan retur
     * (status tracked + akun) tanpa membocorkan model Product penuh.
     * Id akun apa adanya (bisa null = pakai akun default); validitas
     * akun ditegakkan ulang oleh Accounting saat menjurnal.
     *
     * @return array{variant_id: int, product_id: int, is_tracked: bool, inventory_account_id: int|null, purchase_account_id: int|null}|null
     */
    public function execute(int $variantId): ?array
    {
        $variant = ProductVariant::with('product')->find($variantId);

        if (! $variant || ! $variant->product) {
            return null;
        }

        return [
            'variant_id' => (int) $variant->id,
            'product_id' => (int) $variant->product_id,
            'is_tracked' => (bool) $variant->product->is_inventory_tracked,
            'inventory_account_id' => $variant->product->inventory_account_id !== null
                ? (int) $variant->product->inventory_account_id
                : null,
            'purchase_account_id' => $variant->product->purchase_account_id !== null
                ? (int) $variant->product->purchase_account_id
                : null,
        ];
    }
}
