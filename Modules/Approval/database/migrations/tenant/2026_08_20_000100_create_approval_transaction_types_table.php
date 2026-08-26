<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_transaction_types', function (Blueprint $table) {
            $table->id();
            $table->string('module', 40);
            $table->string('key', 60)->unique();
            $table->string('label', 120);
            $table->string('criteria_basis', 20)->default('nominal');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_transaction_types');
    }
};
