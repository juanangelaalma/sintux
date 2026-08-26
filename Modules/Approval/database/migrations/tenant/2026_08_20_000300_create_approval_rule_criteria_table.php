<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rule_criteria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_rule_id')->constrained('approval_rules')->cascadeOnDelete();
            $table->string('criteria_type', 30)->default('amount');
            $table->decimal('min_amount', 15, 4)->nullable();
            $table->unsignedInteger('sequence')->default(1);
            $table->timestamps();

            $table->index(['approval_rule_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rule_criteria');
    }
};
