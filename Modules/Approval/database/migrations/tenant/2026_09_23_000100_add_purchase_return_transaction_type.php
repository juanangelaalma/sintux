<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tipe transaksi Retur Pembelian untuk tenant yang sudah migrate
     * sebelum tipe ini ada. Basis nominal: rule dicocokkan terhadap
     * total nilai retur (returned_gross).
     */
    public function up(): void
    {
        DB::table('approval_transaction_types')->updateOrInsert(
            ['key' => 'purchase_return'],
            [
                'module' => 'purchasing',
                'key' => 'purchase_return',
                'label' => 'Retur Pembelian',
                'criteria_basis' => 'nominal',
            ],
        );
    }

    public function down(): void
    {
        DB::table('approval_transaction_types')->where('key', 'purchase_return')->delete();
    }
};
