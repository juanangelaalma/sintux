<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referensi dokumen penagih di faktur hutang HO:
     * - supplier_invoice_no: no. invoice dagang supplier (prefill dari DO
     *   via BranchReception, readonly). Unik per supplier agar faktur yang
     *   sama tidak dicatat dua kali.
     * - tax_invoice_no: no. faktur pajak (e-Faktur) untuk klaim PPN Masukan.
     *   Unik per supplier. Nullable: supplier non-PKP / pajak menyusul.
     *
     * Semua nullable + unique per supplier (Postgres mengizinkan multi-NULL,
     * jadi faktur lama/manual tanpa nomor tetap aman).
     */
    public function up(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->string('supplier_invoice_no', 100)->nullable()->after('goods_receipt_id');
            $table->string('tax_invoice_no', 100)->nullable()->after('supplier_invoice_no');
            $table->unique(['supplier_id', 'supplier_invoice_no'], 'purchase_invoices_supplier_inv_unique');
            $table->unique(['supplier_id', 'tax_invoice_no'], 'purchase_invoices_tax_inv_unique');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            $table->dropUnique('purchase_invoices_supplier_inv_unique');
            $table->dropUnique('purchase_invoices_tax_inv_unique');
            $table->dropColumn(['supplier_invoice_no', 'tax_invoice_no']);
        });
    }
};
