<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rule_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_rule_id')->constrained('approval_rules')->cascadeOnDelete();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->string('change_type', 30);
            $table->json('before_value')->nullable();
            $table->json('after_value')->nullable();
            $table->timestamp('changed_at')->useCurrent();
            $table->timestamps();

            $table->index(['approval_rule_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rule_logs');
    }
};
