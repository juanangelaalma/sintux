<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_invoice_id')->constrained('sales_invoices')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->string('product_name');
            $table->string('sku');
            $table->string('uom_name')->nullable();
            $table->integer('qty');
            $table->decimal('unit_price', 15, 4)->default(0);
            $table->string('discount_type', 10)->nullable();
            $table->decimal('discount_value', 15, 4)->default(0);
            $table->decimal('discount_amount', 15, 4)->default(0);
            $table->decimal('line_gross', 15, 4)->default(0);
            $table->decimal('line_net', 15, 4)->default(0);
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('line_total', 15, 4)->default(0);
            $table->timestamps();

            $table->index('sales_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoice_items');
    }
};
