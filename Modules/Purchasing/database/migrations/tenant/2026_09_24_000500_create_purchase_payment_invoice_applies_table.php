<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jejak payment yang sudah diterapkan ke faktur + kunci idempotensi per
     * reference (payment). Satu (invoice, reference) hanya naikkan
     * paid_amount sekali walau finalize dijalankan ulang.
     */
    public function up(): void
    {
        Schema::create('purchase_payment_invoice_applies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->string('reference_type', 100);
            $table->unsignedBigInteger('reference_id');
            $table->decimal('amount', 15, 4);
            $table->timestamps();

            $table->unique(['purchase_invoice_id', 'reference_type', 'reference_id'], 'ppia_ref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payment_invoice_applies');
    }
};
