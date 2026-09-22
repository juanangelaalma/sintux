<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Strict 1 faktur = 1 GRN untuk HO.
     * - Snapshot harga DO supplier di GRN item (dari reception line).
     * - Satu GRN tepat satu faktur (unique, nullable agar faktur lama aman).
     */
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            if (! Schema::hasColumn('goods_receipt_items', 'unit_price_supplier')) {
                $table->decimal('unit_price_supplier', 20, 4)->default(0)->after('qty_received');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            // Unique dengan nullable: di Postgres multiple NULL tetap boleh,
            // jadi faktur mandiri lama tanpa GRN tidak rusak.
            $table->unique('goods_receipt_id', 'purchase_invoices_goods_receipt_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropUnique('purchase_invoices_goods_receipt_id_unique');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropColumn('unit_price_supplier');
        });
    }
};
