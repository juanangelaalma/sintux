<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alasan payment gagal difinalisasi (mis. sisa tagihan sudah terpakai
     * payment lain selagi menunggu approval). Tanpa ini, finalize yang
     * ditolak hanya jadi exception di listener sehingga approval terlihat
     * disetujui padahal efeknya tidak terjadi.
     */
    public function up(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_payments', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_payments', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_payments', 'failure_reason')) {
                $table->dropColumn('failure_reason');
            }
        });
    }
};
