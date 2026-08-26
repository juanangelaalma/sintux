<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('supplier_id')->constrained('warehouses');
            $table->string('payment_term', 50)->nullable()->after('status');
            $table->date('due_date')->nullable()->after('order_date');
            $table->boolean('is_tax_inclusive')->default(false)->after('currency_code');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->dropColumn(['warehouse_id', 'payment_term', 'due_date', 'is_tax_inclusive']);
        });
    }
};
