<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tautkan RTRF ke transfer outbound asalnya secara eksplisit.
     *
     * Kolom nullable agar transfer lama tetap valid. RTRF baru wajib
     * mengisinya lewat CreateReturnStockTransfer; daftar RTRF turunan
     * bisa dibaca langsung tanpa menyimpulkan dari arah gudang.
     */
    public function up(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_transfers', 'origin_transfer_id')) {
                $table->foreignId('origin_transfer_id')->nullable()->after('source_id')
                    ->constrained('stock_transfers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            if (Schema::hasColumn('stock_transfers', 'origin_transfer_id')) {
                $table->dropForeign(['origin_transfer_id']);
                $table->dropColumn('origin_transfer_id');
            }
        });
    }
};
