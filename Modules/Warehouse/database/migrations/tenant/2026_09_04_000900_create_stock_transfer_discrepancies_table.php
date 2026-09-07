<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfer_discrepancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();
            $table->foreignId('stock_transfer_item_id')->constrained('stock_transfer_items')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->decimal('shipped_qty', 15, 4);
            $table->decimal('received_qty', 15, 4);
            $table->decimal('difference_qty', 15, 4);
            $table->text('reason')->nullable();
            $table->string('status')->default('pending'); // pending, investigating, resolved
            $table->text('resolution_note')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['stock_transfer_id']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_discrepancies');
    }
};
