<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Produk hasil auto-mapping DO supplier dibuat tanpa kategori/satuan;
     * dilengkapi manual di modul Produk oleh user.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        DB::statement('ALTER TABLE products ALTER COLUMN category_id DROP NOT NULL');
        DB::statement('ALTER TABLE products ALTER COLUMN uom_id DROP NOT NULL');
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        DB::statement('ALTER TABLE products ALTER COLUMN category_id SET NOT NULL');
        DB::statement('ALTER TABLE products ALTER COLUMN uom_id SET NOT NULL');
    }
};
