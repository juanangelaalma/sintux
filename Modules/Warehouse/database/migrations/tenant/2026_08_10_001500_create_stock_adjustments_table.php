<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('adjustment_number')->unique();
            $table->string('type'); // 'in' or 'out'
            $table->string('status')->default('draft'); // 'draft', 'posted', 'cancelled'
            $table->text('note')->nullable();
            $table->foreignId('adjusted_by')->constrained('users');
            $table->timestamp('adjusted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
