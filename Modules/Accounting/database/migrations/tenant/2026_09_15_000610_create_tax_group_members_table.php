<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_group_id')->constrained('taxes')->cascadeOnDelete();
            $table->foreignId('member_tax_id')->constrained('taxes')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->boolean('is_compound')->default(false);
            $table->timestamps();

            $table->unique(['tax_group_id', 'member_tax_id']);
            $table->unique(['tax_group_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_group_members');
    }
};
