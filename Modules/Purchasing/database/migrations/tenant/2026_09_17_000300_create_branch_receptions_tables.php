<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branch_receptions', function (Blueprint $table) {
            $table->id();
            $table->string('number', 40);
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->foreignId('supplier_id')->constrained('contacts');
            $table->string('supplier_do_no', 60);
            $table->string('supplier_invoice_no', 60)->nullable();
            $table->string('po_no', 60)->nullable();
            $table->date('do_date')->nullable();
            $table->string('cust_name', 255)->nullable();
            $table->string('driver', 255)->nullable();
            $table->string('nopol', 50)->nullable();
            $table->string('transaction_type', 50)->nullable();
            $table->string('status')->default('draft');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('decided_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')->nullOnDelete();
            $table->timestamp('transferred_at')->nullable();
            $table->text('transfer_error')->nullable();
            $table->jsonb('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'number']);
            $table->unique('supplier_do_no');
            $table->index(['branch_id', 'status']);
            $table->index(['purchase_order_id', 'status']);
        });

        Schema::create('branch_reception_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_reception_id')->constrained('branch_receptions')->cascadeOnDelete();
            $table->string('supplier_barcode', 100);
            $table->string('product_name', 255)->default('');
            $table->string('sku', 50);
            $table->string('size', 50)->nullable();
            $table->string('uom_name', 50)->nullable();
            $table->decimal('qty_do', 16, 4)->default(0);
            $table->decimal('qty_received', 16, 4)->default(0);
            $table->boolean('qty_confirmed')->default(false);
            $table->decimal('unit_price_supplier', 20, 4)->default(0);
            $table->string('verification_status', 20)->default('not_verified');
            $table->string('scanned_barcode', 100)->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_reception_id', 'supplier_barcode']);
            $table->index(['branch_reception_id', 'verification_status']);
        });

        Schema::create('branch_reception_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_reception_line_id')->constrained('branch_reception_lines')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('product_name', 255)->default('');
            $table->string('sku', 50);
            $table->string('color_raw', 100)->nullable();
            $table->string('color', 100)->nullable();
            $table->decimal('qty_do', 16, 4)->default(0);
            $table->decimal('qty_received', 16, 4)->default(0);
            $table->timestamps();

            $table->index('branch_reception_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_reception_details');
        Schema::dropIfExists('branch_reception_lines');
        Schema::dropIfExists('branch_receptions');
    }
};
