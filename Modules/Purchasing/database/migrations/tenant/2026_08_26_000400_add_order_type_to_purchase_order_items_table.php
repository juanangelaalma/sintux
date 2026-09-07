<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            // 'regular' = pembelian biasa (milik perusahaan saat diterima)
            // 'consignment' = titipan supplier (utang baru muncul saat terjual)
            $table->string('order_type')->default('regular')->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('order_type');
        });
    }
};
