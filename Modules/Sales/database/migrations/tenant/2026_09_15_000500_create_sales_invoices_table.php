<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40);
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('customer_id')->constrained('contacts');
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('transaction_type', 20);
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('warehouse_code');
            $table->string('warehouse_name');
            $table->foreignId('salesperson_id')->constrained('contacts');
            $table->string('salesperson_name');
            $table->string('payment_term', 50)->nullable();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->string('status')->default('pending');
            $table->string('currency_code', 3)->default('IDR');
            $table->boolean('is_tax_inclusive')->default(false);
            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('line_discount_total', 15, 4)->default(0);
            $table->string('invoice_discount_type', 10)->nullable();
            $table->decimal('invoice_discount_value', 15, 4)->default(0);
            $table->decimal('invoice_discount_amount', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->decimal('total', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_invoices');
    }
};
