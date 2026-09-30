<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('expense_expense_tag', function (Blueprint $table) {
            $table->foreignId('expense_id')->constrained('expenses')->cascadeOnDelete();
            $table->foreignId('expense_tag_id')->constrained('expense_tags')->cascadeOnDelete();

            $table->primary(['expense_id', 'expense_tag_id']);
        });

        $now = now();

        DB::table('expense_tags')->insert([
            ['name' => 'Operasional', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Perjalanan Dinas', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Utilitas', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Sewa', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Perlengkapan', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_expense_tag');
        Schema::dropIfExists('expense_tags');
    }
};
