<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_mapping_id')->constrained('approval_mappings')->cascadeOnDelete();
            $table->foreignId('approval_stage_id')->constrained('approval_stages');
            $table->unsignedBigInteger('user_id');
            $table->string('action', 10);
            $table->text('comment')->nullable();
            $table->timestamp('acted_at')->useCurrent();
            $table->timestamps();

            $table->unique(['approval_mapping_id', 'approval_stage_id', 'user_id']);
            $table->index(['approval_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
    }
};
