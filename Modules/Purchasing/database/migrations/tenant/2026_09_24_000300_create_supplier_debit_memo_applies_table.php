<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak pemakaian debit memo + kunci idempotensi: satu (memo,
     * reference) hanya boleh mengurangi remaining sekali.
     */
    public function up(): void
    {
        Schema::create('supplier_debit_memo_applies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_debit_memo_id')->constrained('supplier_debit_memos')->cascadeOnDelete();
            $table->string('reference_type', 100);
            $table->unsignedBigInteger('reference_id');
            $table->decimal('amount', 15, 4);
            $table->timestamps();

            $table->unique(['supplier_debit_memo_id', 'reference_type', 'reference_id'], 'sdma_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_debit_memo_applies');
    }
};
