<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->unsignedInteger('position');

            $table->foreignId('account_id')->constrained('chart_of_accounts');
            // Snapshot: nilai historis tidak boleh ikut berubah saat CoA
            // direname. Sama seperti AGENTS.md §13 untuk nilai transaksi.
            $table->string('account_name', 150);

            $table->text('description')->nullable();

            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->decimal('tax_rate', 8, 4)->default(0);

            // amount = nominal apa adanya seperti diinput user.
            $table->decimal('amount', 15, 4)->default(0);
            $table->decimal('amount_before_tax', 15, 4)->default(0);
            $table->decimal('tax_amount', 15, 4)->default(0);
            $table->jsonb('tax_breakdown')->nullable();

            $table->timestamps();

            $table->index(['expense_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_lines');
    }
};
