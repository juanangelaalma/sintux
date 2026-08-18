<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40);
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('supplier_id')->constrained('contacts');
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('status')->default('draft');
            $table->date('receipt_date');
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->index(['branch_id', 'status']);
            $table->index(['purchase_order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
