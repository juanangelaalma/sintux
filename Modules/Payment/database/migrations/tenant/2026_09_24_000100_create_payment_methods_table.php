<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 50)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();

        DB::table('payment_methods')->insert([
            ['name' => 'Kas Tunai', 'code' => 'cash', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Cek & Giro', 'code' => 'cheque', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Transfer Bank', 'code' => 'bank_transfer', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Kartu Kredit', 'code' => 'credit_card', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
