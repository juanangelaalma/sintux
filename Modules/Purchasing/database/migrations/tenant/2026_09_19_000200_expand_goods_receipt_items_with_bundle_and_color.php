<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GRN tunggal: tiap baris = satu (bundle barcode, warna). Kolom
     * level-bundle didenormalisasi per baris warna; warna di-snapshot agar
     * histori GRN dan faktur bisa direkonsiliasi per warna.
     */
    public function up(): void
    {
        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->string('supplier_barcode', 100)->nullable()->after('goods_receipt_id');
            $table->string('size', 50)->nullable()->after('sku');
            $table->string('color_raw', 100)->nullable()->after('size');
            $table->string('color', 100)->nullable()->after('color_raw');
            $table->decimal('qty_do', 16, 4)->default(0)->after('color');
            $table->boolean('qty_confirmed')->default(false)->after('qty_received');
            $table->string('verification_status', 20)->default('not_verified')->after('qty_confirmed');
            $table->string('scanned_barcode', 100)->nullable()->after('verification_status');
            $table->timestamp('scanned_at')->nullable()->after('scanned_barcode');

            $table->index(['goods_receipt_id', 'verification_status']);
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->string('color_raw', 100)->nullable()->after('sku');
            $table->string('color', 100)->nullable()->after('color_raw');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['color_raw', 'color']);
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropIndex(['goods_receipt_id', 'verification_status']);
            $table->dropColumn([
                'supplier_barcode',
                'size',
                'color_raw',
                'color',
                'qty_do',
                'qty_confirmed',
                'verification_status',
                'scanned_barcode',
                'scanned_at',
            ]);
        });
    }
};
