<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah tipe transaksi Transfer Stok untuk tenant yang sudah migrate
     * sebelum tipe ini ada di seeder awal.
     */
    public function up(): void
    {
        DB::table('approval_transaction_types')->updateOrInsert(
            ['key' => 'stock_transfer'],
            [
                'module' => 'warehouse',
                'key' => 'stock_transfer',
                'label' => 'Transfer Stok',
                'criteria_basis' => 'quantity',
            ],
        );
    }

    public function down(): void
    {
        DB::table('approval_transaction_types')->where('key', 'stock_transfer')->delete();
    }
};
