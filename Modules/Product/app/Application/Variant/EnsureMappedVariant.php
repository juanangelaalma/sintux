<?php

namespace Modules\Product\Application\Variant;

use Illuminate\Support\Facades\DB;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductVariant;

/**
 * Mapping mandiri varian supplier → master internal (dipicu tombol
 * "Mapping & Buatkan Produk" di halaman Penerimaan cabang).
 *
 * Public cross-module API: Purchasing memetakan (prd_code, warna) tanpa
 * menyentuh tabel product langsung. Master dibuat di scope cabang pemilik
 * (cabang PO, umumnya HO) agar approval HO bisa menyetoknya; mirror ke
 * cabang penerima terjadi otomatis saat stock transfer diterima.
 *
 * @param array{
 *     owner_branch_id: int,
 *     sku: string,
 *     color: string|null,
 *     product_name: string,
 *     category_id?: int|null,
 *     uom_id?: int|null,
 *     purchase_price?: float,
 *     supplier_do_no?: string|null
 * } $data
 */
class EnsureMappedVariant
{
    public function __construct(
        private readonly FindVariantBySkuAndColor $finder,
    ) {}

    public function execute(array $data): ProductVariant
    {
        $ownerBranchId = (int) $data['owner_branch_id'];
        $sku = trim((string) $data['sku']);
        $colorRaw = $data['color'] ?? null;
        $color = FindVariantBySkuAndColor::normalizeColor($colorRaw);

        $existing = $this->finder->execute($sku, $colorRaw, $ownerBranchId);

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($ownerBranchId, $sku, $color, $colorRaw, $data) {
            $locked = $this->finder->execute($sku, $colorRaw, $ownerBranchId);

            if ($locked) {
                return $locked;
            }

            $product = Product::query()
                ->where('branch_id', $ownerBranchId)
                ->where('code', $sku)
                ->first();

            if (! $product) {
                $product = Product::create([
                    'branch_id' => $ownerBranchId,
                    'code' => $sku,
                    'name' => $data['product_name'],
                    'category_id' => $data['category_id'] ?? null,
                    'uom_id' => $data['uom_id'] ?? null,
                    'is_purchased' => true,
                    'purchase_price' => $data['purchase_price'] ?? 0,
                    'is_sold' => true,
                    'is_inventory_tracked' => true,
                    'is_active' => true,
                ]);
            }

            $variantName = $color !== ''
                ? $product->name.' - '.(is_string($colorRaw) ? trim((string) $colorRaw) : $color)
                : $product->name;

            return ProductVariant::create([
                'branch_id' => $ownerBranchId,
                'product_id' => $product->id,
                'sku' => $sku,
                'variant_name' => $variantName,
                'attributes' => array_filter([
                    'color' => $color,
                    'color_raw' => is_string($colorRaw) ? trim($colorRaw) : null,
                    'source' => 'supplier_do',
                    'supplier_do_no' => $data['supplier_do_no'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
                'is_active' => true,
            ]);
        });
    }
}
