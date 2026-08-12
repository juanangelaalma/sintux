<?php

declare(strict_types=1);

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
        Schema::create('coa_account_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('normal_balance', 10);
            $table->string('financial_statement', 30);
            $table->smallInteger('sort_order')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coa_account_types');
    }
};
