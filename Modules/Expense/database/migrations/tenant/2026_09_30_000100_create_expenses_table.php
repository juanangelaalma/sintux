<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();

            // PRD memakai company_id; repo mengisolasi tenant lewat database
            // per tenant dan scoping antar cabang lewat branch_id.
            $table->foreignId('branch_id')->constrained('branches');

            $table->string('number', 60);
            // Sumber penomoran otomatis (BR-04). Dipisah dari number karena
            // kolom number boleh berisi teks bebas dari user, sedangkan
            // sequence harus selalu numerik agar aman diurutkan.
            $table->unsignedInteger('sequence');

            $table->date('transaction_date');

            $table->foreignId('pay_from_account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();

            $table->boolean('is_pay_later')->default(false);

            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->string('contact_name', 150)->nullable();
            $table->text('billing_address')->nullable();

            $table->foreignId('payment_method_id')
                ->nullable()
                ->constrained('payment_methods')
                ->nullOnDelete();

            $table->string('currency_code', 3)->default('IDR');
            $table->boolean('is_tax_inclusive')->default(false);

            $table->string('withholding_type', 10)->nullable();
            $table->decimal('withholding_value', 15, 4)->default(0);
            $table->foreignId('withholding_account_id')
                ->nullable()
                ->constrained('chart_of_accounts')
                ->nullOnDelete();
            $table->decimal('withholding_total', 15, 4)->default(0);

            $table->text('memo')->nullable();

            $table->decimal('subtotal', 15, 4)->default(0);
            $table->decimal('tax_total', 15, 4)->default(0);
            $table->decimal('grand_total', 15, 4)->default(0);
            $table->decimal('amount_paid', 15, 4)->default(0);

            $table->string('status', 20)->default('closed');
            $table->string('source', 20)->default('manual');

            // users adalah tabel central, jadi tidak bisa jadi foreign key
            // dari schema tenant. Konsisten dengan purchase_payments.
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Unik per tenant, bukan per cabang: penomoran BR-04 berurutan
            // lintas seluruh cabang dalam satu tenant.
            $table->unique('number');
            $table->unique('sequence');

            $table->index(['branch_id', 'transaction_date']);
            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
