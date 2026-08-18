<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('join_purchase_invoices', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40);
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('status')->default('draft');
            $table->date('join_date');
            $table->text('note')->nullable();
            $table->string('currency_code', 3)->default('IDR');
            $table->decimal('total_amount', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('join_purchase_invoices');
    }
};
