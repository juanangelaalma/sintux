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
        Schema::create('coa_account_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('account_type_id');
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            $table->smallInteger('sort_order')->nullable();
            $table->timestamps();

            $table->foreign('account_type_id')
                ->references('id')
                ->on('coa_account_types');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coa_account_categories');
    }
};
