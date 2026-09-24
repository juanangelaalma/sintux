<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen pembayaran pembelian. Satu tabel untuk dua mode:
     * - invoice : alokasi ke faktur (isi di Task 6)
     * - deposit : uang muka supplier (isi di Task 5)
     */
    public function up(): void
    {
        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->id();
            $table->string('number', 60)->unique();
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('supplier_id')->constrained('contacts');
            $table->foreignId('payment_method_id')->nullable()->constrained('payment_methods')->nullOnDelete();
            $table->string('mode', 20)->default('invoice');
            $table->date('payment_date');
            $table->date('due_date')->nullable();
            $table->string('currency_code', 3)->default('IDR');
            $table->foreignId('cash_account_id')->constrained('chart_of_accounts');
            $table->decimal('gross_amount', 15, 4)->default(0);
            $table->decimal('withholding_amount', 15, 4)->default(0);
            $table->decimal('deposit_applied', 15, 4)->default(0);
            $table->decimal('memo_applied', 15, 4)->default(0);
            $table->decimal('cash_out', 15, 4)->default(0);
            $table->decimal('deposit_total', 15, 4)->default(0);
            $table->decimal('deposit_remaining', 15, 4)->default(0);
            $table->string('status', 20)->default('pending');
            $table->text('memo')->nullable();
            // users adalah tabel central (bukan tenant), jadi tanpa FK.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
            $table->index(['branch_id', 'payment_date']);
        });

        Schema::create('purchase_payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_payment_id')->constrained('purchase_payments')->cascadeOnDelete();
            $table->foreignId('purchase_invoice_id')->constrained('purchase_invoices')->cascadeOnDelete();
            $table->decimal('amount', 15, 4);
            $table->timestamps();

            $table->index('purchase_payment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_payment_allocations');
        Schema::dropIfExists('purchase_payments');
    }
};
