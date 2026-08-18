<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->string('product_name');
            $table->string('sku');
            $table->string('uom_name')->nullable();
            $table->decimal('qty_requested', 15, 4);
            $table->decimal('qty_ordered', 15, 4)->default(0);
            $table->decimal('unit_price', 15, 4)->nullable();
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->nullable();
            $table->decimal('line_total', 15, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
