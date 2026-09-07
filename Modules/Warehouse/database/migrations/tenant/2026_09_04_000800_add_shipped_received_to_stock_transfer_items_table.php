<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->decimal('qty_shipped', 15, 4)->nullable()->after('cost');
            $table->decimal('qty_received', 15, 4)->nullable()->after('qty_shipped');
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn(['qty_shipped', 'qty_received']);
        });
    }
};
