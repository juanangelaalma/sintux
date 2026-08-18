<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('join_purchase_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('join_purchase_invoice_id')->constrained('join_purchase_invoices')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices');
            $table->foreignId('supplier_id')->constrained('contacts');
            $table->string('invoice_number', 40);
            $table->string('supplier_name');
            $table->decimal('invoice_total', 15, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('join_purchase_invoice_items');
    }
};
