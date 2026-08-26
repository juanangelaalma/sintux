<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_stage_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_stage_id')->constrained('approval_stages')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamps();

            $table->unique(['approval_stage_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_stage_approvers');
    }
};
