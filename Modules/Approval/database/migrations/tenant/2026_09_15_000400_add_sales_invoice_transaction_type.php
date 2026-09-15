<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah tipe transaksi Faktur Penjualan untuk tenant yang sudah
     * migrate sebelum tipe ini ada di seeder awal. Basis nominal:
     * rule approval dicocokkan terhadap TOTAL akhir faktur.
     */
    public function up(): void
    {
        DB::table('approval_transaction_types')->updateOrInsert(
            ['key' => 'sales_invoice'],
            [
                'module' => 'sales',
                'key' => 'sales_invoice',
                'label' => 'Faktur Penjualan',
                'criteria_basis' => 'nominal',
            ],
        );
    }

    public function down(): void
    {
        DB::table('approval_transaction_types')->where('key', 'sales_invoice')->delete();
    }
};
