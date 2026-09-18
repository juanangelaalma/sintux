<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * GRN tunggal: serap header DO supplier + kolom workflow approval yang
     * dulu hidup di branch_receptions. Gudang administratif (HO Regular)
     * dan tanggal terima diisi sejak fetch agar kolom tetap required.
     */
    public function up(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->string('supplier_do_no', 60)->after('purchase_order_id');
            $table->string('supplier_invoice_no', 60)->nullable()->after('supplier_do_no');
            $table->string('po_no', 60)->nullable()->after('supplier_invoice_no');
            $table->date('do_date')->nullable()->after('po_no');
            $table->string('cust_name', 255)->nullable()->after('do_date');
            $table->string('driver', 255)->nullable()->after('cust_name');
            $table->string('nopol', 50)->nullable()->after('driver');
            $table->string('transaction_type', 50)->nullable()->after('nopol');
            $table->unsignedBigInteger('submitted_by')->nullable()->after('status');
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
            $table->unsignedBigInteger('decided_by')->nullable()->after('submitted_at');
            $table->timestamp('decided_at')->nullable()->after('decided_by');
            $table->text('rejection_reason')->nullable()->after('decided_at');
            $table->timestamp('transferred_at')->nullable()->after('rejection_reason');
            $table->text('transfer_error')->nullable()->after('transferred_at');
            $table->jsonb('raw_payload')->nullable()->after('transfer_error');

            $table->unique('supplier_do_no');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->dropUnique(['supplier_do_no']);
            $table->dropColumn([
                'supplier_do_no',
                'supplier_invoice_no',
                'po_no',
                'do_date',
                'cust_name',
                'driver',
                'nopol',
                'transaction_type',
                'submitted_by',
                'submitted_at',
                'decided_by',
                'decided_at',
                'rejection_reason',
                'transferred_at',
                'transfer_error',
                'raw_payload',
            ]);
        });
    }
};
