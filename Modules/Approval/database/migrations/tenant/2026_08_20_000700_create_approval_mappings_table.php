<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 60);
            $table->unsignedBigInteger('transaction_id');
            $table->foreignId('approval_rule_id')->constrained('approval_rules');
            $table->string('document_number', 60)->nullable();
            $table->unsignedBigInteger('creator_id');
            $table->string('creator_name', 150)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->decimal('total', 15, 4)->nullable();
            $table->string('currency_code', 3)->default('IDR');
            $table->unsignedTinyInteger('current_stage_order')->default(1);
            $table->string('overall_status', 20)->default('pending');
            $table->timestamp('mapped_at')->useCurrent();
            $table->timestamps();

            $table->unique(['transaction_type', 'transaction_id']);
            $table->index(['overall_status']);
            $table->index(['transaction_type', 'overall_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_mappings');
    }
};
