<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('number', 60)->unique();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('supplier_id')->constrained('contacts');
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('status')->default('pending');
            $table->date('return_date');
            $table->text('message')->nullable();
            $table->text('memo')->nullable();
            $table->string('currency_code', 3)->default('IDR');
            $table->boolean('is_tax_inclusive')->default(false);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index('supplier_id');
            $table->index('purchase_invoice_id');
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_item_id')->constrained('purchase_invoice_items');
            $table->foreignId('product_variant_id')->constrained('product_variants');
            $table->string('product_name');
            $table->string('sku');
            $table->string('uom_name')->nullable();
            $table->decimal('qty', 15, 4);
            $table->decimal('unit_price', 15, 4);
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->default(0);
            $table->jsonb('tax_breakdown')->nullable();
            $table->decimal('line_total', 15, 4)->default(0);
            $table->timestamps();

            $table->index('purchase_return_id');
        });

        Schema::create('supplier_debit_memos', function (Blueprint $table) {
            $table->id();
            $table->string('number', 60)->unique();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('supplier_id')->constrained('contacts');
            $table->foreignId('purchase_return_id')->nullable()->constrained('purchase_returns')->nullOnDelete();
            $table->foreignId('purchase_invoice_id')->nullable()->constrained('purchase_invoices')->nullOnDelete();
            $table->string('status')->default('open');
            $table->decimal('total', 15, 4)->default(0);
            $table->decimal('remaining', 15, 4)->default(0);
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index('purchase_return_id');
        });

        Schema::create('purchase_return_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();

            $table->index('purchase_return_id');
        });

        Schema::create('purchase_return_purchase_tag', function (Blueprint $table) {
            $table->foreignId('purchase_return_id')->constrained('purchase_returns')->cascadeOnDelete();
            $table->foreignId('purchase_tag_id')->constrained('purchase_tags')->cascadeOnDelete();

            $table->primary(['purchase_return_id', 'purchase_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_return_purchase_tag');
        Schema::dropIfExists('purchase_return_attachments');
        Schema::dropIfExists('supplier_debit_memos');
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
    }
};
