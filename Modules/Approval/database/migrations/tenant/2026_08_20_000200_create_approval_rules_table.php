<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_type_id')->constrained('approval_transaction_types');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('currency_code', 3)->default('IDR');
            $table->boolean('scope_all_users')->default(true);
            $table->boolean('apply_to_existing_draft')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['transaction_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_rules');
    }
};
