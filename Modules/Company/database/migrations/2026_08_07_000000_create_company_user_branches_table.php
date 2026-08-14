<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_user_branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_user_id')->constrained('company_users')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id');
            $table->timestamps();

            $table->unique(['company_user_id', 'branch_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_user_branches');
    }
};
