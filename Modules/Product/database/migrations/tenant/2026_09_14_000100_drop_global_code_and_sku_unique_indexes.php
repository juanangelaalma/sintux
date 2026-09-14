<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Drop the legacy global unique indexes on products.code and
     * product_variants.sku so branches can mirror the same code/sku
     * under their branch-scoped unique constraints.
     */
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            DB::statement('DROP INDEX IF EXISTS products_code_unique');
        }

        if (Schema::hasTable('product_variants')) {
            DB::statement('DROP INDEX IF EXISTS product_variants_sku_unique');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products')) {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS products_code_unique ON products (code) WHERE deleted_at IS NULL'
            );
        }

        if (Schema::hasTable('product_variants')) {
            DB::statement(
                'CREATE UNIQUE INDEX IF NOT EXISTS product_variants_sku_unique ON product_variants (sku) WHERE deleted_at IS NULL'
            );
        }
    }
};
