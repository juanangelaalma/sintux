<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('order_type')->default('regular')->after('source_request_id');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('order_type');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->string('order_type')->default('regular')->after('description');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('order_type');
        });
    }
};
