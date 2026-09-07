<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_tags', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 20)->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_purchase_tag', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('purchase_tag_id')->constrained('purchase_tags')->cascadeOnDelete();

            $table->primary(['purchase_order_id', 'purchase_tag_id']);
        });

        $now = now();

        DB::table('purchase_tags')->insert([
            ['name' => 'Reguler', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Konsinyasi', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Urgent', 'color' => '#ef4444', 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Impor', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Lokal', 'color' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_purchase_tag');
        Schema::dropIfExists('purchase_tags');
    }
};
