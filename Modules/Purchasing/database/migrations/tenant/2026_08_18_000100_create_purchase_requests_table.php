<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40);
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('supplier_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->date('request_date');
            $table->date('expected_date')->nullable();
            $table->text('note')->nullable();
            $table->string('currency_code', 3)->default('IDR');
            $table->decimal('subtotal', 15, 4)->nullable();
            $table->decimal('tax_amount', 15, 4)->nullable();
            $table->decimal('total', 15, 4)->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
