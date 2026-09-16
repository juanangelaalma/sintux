<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot per-anggota pajak grup [{tax_id, rate, amount}] ditulis saat
     * create — syarat historical correctness (AGENTS §13).
     *
     * @var list<string>
     */
    private const TABLES = [
        'purchase_request_items',
        'purchase_quote_items',
        'purchase_order_items',
        'purchase_invoice_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->jsonb('tax_breakdown')->nullable()->after('tax_rate');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropColumn('tax_breakdown');
            });
        }
    }
};
