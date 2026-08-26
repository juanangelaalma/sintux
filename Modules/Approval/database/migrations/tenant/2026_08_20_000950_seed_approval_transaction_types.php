<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Reference data required by every tenant. Seeded here so it is present
     * after `tenants:migrate` without relying on a manual tenant seed run.
     */
    public function up(): void
    {
        $types = [
            ['module' => 'purchasing', 'key' => 'purchase_request', 'label' => 'Permintaan Pembelian', 'criteria_basis' => 'nominal'],
            ['module' => 'purchasing', 'key' => 'purchase_order', 'label' => 'Pesanan Pembelian', 'criteria_basis' => 'nominal'],
            ['module' => 'purchasing', 'key' => 'purchase_invoice', 'label' => 'Faktur Pembelian', 'criteria_basis' => 'nominal'],
        ];

        foreach ($types as $type) {
            DB::table('approval_transaction_types')->updateOrInsert(
                ['key' => $type['key']],
                $type,
            );
        }
    }

    public function down(): void
    {
        DB::table('approval_transaction_types')->whereIn('key', [
            'purchase_request',
            'purchase_order',
            'purchase_invoice',
        ])->delete();
    }
};
