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
        Schema::create('chart_of_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->foreignId('account_category_id');
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('is_header')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index('parent_id');
            $table->index('account_category_id');

            $table->foreign('parent_id')
                ->references('id')
                ->on('chart_of_accounts');
            $table->foreign('account_category_id')
                ->references('id')
                ->on('coa_account_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chart_of_accounts');
    }
};
