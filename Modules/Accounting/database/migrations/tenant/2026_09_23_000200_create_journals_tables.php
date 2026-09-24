<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->date('journal_date');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->text('memo')->nullable();
            $table->string('status')->default('posted');
            $table->timestamps();

            $table->index(['branch_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('journal_id')->constrained('journals')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->decimal('debit', 15, 4)->default(0);
            $table->decimal('credit', 15, 4)->default(0);
            $table->text('memo')->nullable();
            $table->timestamps();

            $table->index('journal_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journals');
    }
};
