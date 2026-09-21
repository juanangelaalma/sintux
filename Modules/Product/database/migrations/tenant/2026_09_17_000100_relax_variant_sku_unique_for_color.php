<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Longgarkan unique SKU per branch menjadi (branch, sku, warna).
     *
     * Satu prd_code supplier dipakai banyak warna; warna disimpan di
     * attributes->>'color'. Identitas varian = kombinasi ketiganya.
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_variants')) {
            return;
        }

        // Dibuat via $table->unique() sehingga berupa CONSTRAINT, bukan index biasa.
        DB::statement('ALTER TABLE product_variants DROP CONSTRAINT IF EXISTS product_variants_branch_sku_unique');

        // UPPER(...) agar sinkron dengan normalisasi warna di aplikasi
        // (FindVariantBySkuAndColor::normalizeColor): 'Hitam' dan 'HITAM'
        // adalah varian yang sama dan wajib ditolak ganda di DB.
        DB::statement(
            "CREATE UNIQUE INDEX IF NOT EXISTS product_variants_branch_sku_color_unique
             ON product_variants (branch_id, sku, (UPPER(COALESCE(attributes->>'color', ''))))
             WHERE deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_variants')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS product_variants_branch_sku_color_unique');

        Schema::table('product_variants', function (Blueprint $table) {
            $table->unique(['branch_id', 'sku'], 'product_variants_branch_sku_unique');
        });
    }
};
