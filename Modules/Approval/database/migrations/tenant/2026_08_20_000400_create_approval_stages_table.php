<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_rule_id')->constrained('approval_rules')->cascadeOnDelete();
            $table->unsignedTinyInteger('stage_order');
            $table->string('approval_type', 10)->default('any');
            $table->timestamps();

            $table->unique(['approval_rule_id', 'stage_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_stages');
    }
};
