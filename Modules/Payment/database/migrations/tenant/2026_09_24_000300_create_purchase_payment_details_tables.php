<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detail pembayaran: withholding (persen/nominal + akun), pemakaian
     * uang muka, dan pemakaian kredit Debit Memo supplier.
     */
    public function up(): void
    {
        Schema::create('purchase_payment_withholdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_payment_id')->constrained('purchase_payments')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('chart_of_accounts');
            $table->string('type', 20); // percent | nominal
            $table->decimal('value', 18, 4);
            $table->decimal('amount', 15, 4);
            $table->timestamps();

            $table->index('purchase_payment_id');
        });

        Schema::create('purchase_payment_deposit_applies', function (Blueprint $table) {
            $table->id();
            // payment yang memakai kredit
            $table->foreignId('purchase_payment_id')->constrained('purchase_payments')->cascadeOnDelete();
            // payment deposit sumber (mode = deposit)
            $table->foreignId('source_payment_id')->constrained('purchase_payments')->cascadeOnDelete();
            $table->decimal('amount', 15, 4);
            // filled at finalize to mark the credit as actually consumed
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index('purchase_payment_id');
            $table->unique(['purchase_payment_id', 'source_payment_id'], 'ppda_unique');
        });

        Schema::create('purchase_payment_memo_applies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_payment_id')->constrained('purchase_payments')->cascadeOnDelete();
            $table->foreignId('supplier_debit_memo_id')->constrained('supplier_debit_memos')->cascadeOnDelete();
            $table->decimal('amount', 15, 4);
            $table->timestamps();

            $table->index('purchase_payment_id');
        });

        DB::table('approval_transaction_types')->updateOrInsert(
            ['key' => 'purchase_payment'],
            [
                'module' => 'payment',
                'key' => 'purchase_payment',
                'label' => 'Pembayaran Pembelian',
                'criteria_basis' => 'nominal',
            ],
        );
    }

    public function down(): void
    {
        DB::table('approval_transaction_types')->where('key', 'purchase_payment')->delete();

        Schema::dropIfExists('purchase_payment_memo_applies');
        Schema::dropIfExists('purchase_payment_deposit_applies');
        Schema::dropIfExists('purchase_payment_withholdings');
    }
};
