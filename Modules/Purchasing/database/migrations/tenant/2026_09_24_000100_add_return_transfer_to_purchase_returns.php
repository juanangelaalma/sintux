<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_returns', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_returns', 'return_transfer_id')) {
                $table->foreignId('return_transfer_id')->nullable()->after('warehouse_id')->constrained('stock_transfers')->nullOnDelete();
            }
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_return_items', 'stock_variant_id')) {
                // Varian gudang yang di-consume (bisa beda dari varian faktur
                // bila receive transfer membuat cermin varian baru).
                $table->foreignId('stock_variant_id')->nullable()->after('product_variant_id')->constrained('product_variants');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_return_items', 'stock_variant_id')) {
                $table->dropForeign(['stock_variant_id']);
                $table->dropColumn('stock_variant_id');
            }
        });

        Schema::table('purchase_returns', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_returns', 'return_transfer_id')) {
                $table->dropForeign(['return_transfer_id']);
                $table->dropColumn('return_transfer_id');
            }
        });
    }
};
