<?php

namespace Modules\Product\Application\Product;

use Illuminate\Support\Facades\DB;
use Modules\Product\Application\Variant\FindVariantBySkuAndColor;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

class EnsureVariantForBranch
{
    /**
     * Resolve the product variant a branch must use when stocking a
     * product that originates from another branch.
     *
     * Public cross-module API: when the receiving branch has no master
     * data for the source product, a branch-scoped mirror (product and
     * variant) is created. When a master already exists it is reused so
     * the caller simply stocks onto the existing variant.
     *
     * @return int The product_variant_id scoped to the given branch.
     */
    public function execute(int $sourceVariantId, int $branchId): int
    {
        $sourceVariant = ProductVariant::with('product')->findOrFail($sourceVariantId);

        if ((int) $sourceVariant->branch_id === $branchId) {
            return $sourceVariantId;
        }

        return DB::transaction(function () use ($sourceVariant, $branchId): int {
            $product = $this->ensureProduct($sourceVariant->product, $branchId);

            return $this->ensureVariant($sourceVariant, $product, $branchId)->id;
        });
    }

    private function ensureProduct(Product $source, int $branchId): Product
    {
        $existing = Product::query()
            ->where('branch_id', $branchId)
            ->where('code', $source->code)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Product::create([
            'branch_id' => $branchId,
            'code' => $source->code,
            'name' => $source->name,
            'barcode' => $source->barcode,
            'category_id' => $source->category_id,
            'uom_id' => $source->uom_id,
            'description' => $source->description,
            'image_path' => $source->image_path,
            'product_type' => $source->product_type,
            'is_purchased' => $source->is_purchased,
            'purchase_price' => $source->purchase_price,
            'purchase_account_id' => $source->purchase_account_id,
            'purchase_tax_id' => $source->purchase_tax_id,
            'is_sold' => $source->is_sold,
            'selling_price' => $source->selling_price,
            'sales_account_id' => $source->sales_account_id,
            'sales_tax_id' => $source->sales_tax_id,
            'is_inventory_tracked' => $source->is_inventory_tracked,
            'min_stock' => $source->min_stock,
            'inventory_account_id' => $source->inventory_account_id,
            'is_active' => $source->is_active,
        ]);
    }

    private function ensureVariant(
        ProductVariant $source,
        Product $branchProduct,
        int $branchId,
    ): ProductVariant {
        $sourceColor = $source->attributes['color'] ?? null;
        $normalized = FindVariantBySkuAndColor::normalizeColor(is_string($sourceColor) ? $sourceColor : null);

        $existing = ProductVariant::query()
            ->where('branch_id', $branchId)
            ->where('sku', $source->sku)
            ->whereRaw("UPPER(COALESCE(attributes->>'color', '')) = ?", [$normalized])
            ->first();

        if ($existing) {
            return $existing;
        }

        return ProductVariant::create([
            'branch_id' => $branchId,
            'product_id' => $branchProduct->id,
            'sku' => $source->sku,
            'variant_name' => $source->variant_name,
            'attributes' => FindVariantBySkuAndColor::normalizeAttributes($source->attributes),
            'is_active' => $source->is_active,
        ]);
    }
}
