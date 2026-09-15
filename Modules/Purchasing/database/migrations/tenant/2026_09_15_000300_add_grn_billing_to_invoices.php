<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Faktur per GRN: link faktur ke GRN, counter tertagih per baris
     * GRN dan per baris PO, serta mode pajak fleksibel per faktur.
     * Semua kolom baru nullable/berdefault agar data lama aman.
     */
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->foreignId('goods_receipt_id')->nullable()->after('purchase_order_id')->constrained('goods_receipts')->nullOnDelete();
            $table->boolean('is_tax_inclusive')->default(false)->after('currency_code');
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->foreignId('goods_receipt_item_id')->nullable()->after('purchase_order_item_id')->constrained('goods_receipt_items')->nullOnDelete();
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->decimal('qty_invoiced', 15, 4)->default(0)->after('qty_received');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('qty_invoiced', 15, 4)->default(0)->after('qty_received');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('qty_invoiced');
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropColumn('qty_invoiced');
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropForeign(['goods_receipt_item_id']);
            $table->dropColumn('goods_receipt_item_id');
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropForeign(['goods_receipt_id']);
            $table->dropColumn(['goods_receipt_id', 'is_tax_inclusive']);
        });
    }
};
