<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->decimal('qty_remaining', 15, 4)->default(0);
            $table->decimal('unit_cost', 15, 4)->default(0);
            $table->timestamp('received_at');
            $table->string('source_type')->nullable(); // e.g., 'purchase_order', 'stock_transfer', 'adjustment'
            $table->unsignedBigInteger('source_id')->nullable(); // ID of the source document
            $table->timestamps();

            $table->index(['product_variant_id', 'warehouse_id', 'received_at']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_layers');
    }
};
