<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tag pembayaran. Pivot dimiliki Payment, jadi FK hanya ke
     * purchase_payments. purchase_tag_id tidak diberi FK agar Payment tidak
     * terikat ke skema Purchasing; validasi lewat GetPurchaseTags (Application
     * API milik Purchasing).
     */
    public function up(): void
    {
        Schema::create('purchase_payment_purchase_tag', function (Blueprint $table) {
            $table->foreignId('purchase_payment_id')->constrained('purchase_payments')->cascadeOnDelete();
            $table->unsignedBigInteger('purchase_tag_id');

            $table->primary(['purchase_payment_id', 'purchase_tag_id']);
            $table->index('purchase_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payment_purchase_tag');
    }
};
