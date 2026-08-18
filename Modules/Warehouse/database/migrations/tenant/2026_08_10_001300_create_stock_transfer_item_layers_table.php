<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_item_layers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items')->cascadeOnDelete();
            $table->foreignId('stock_layer_id')->constrained('stock_layers');
            $table->decimal('qty_taken', 15, 4);
            $table->decimal('unit_cost', 15, 4);
            $table->timestamps();

            $table->index(['stock_transfer_item_id', 'stock_layer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_item_layers');
    }
};
