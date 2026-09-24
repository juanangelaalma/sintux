<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kredibil: ListSupplierDebitMemos selalu memfilter remaining > 0, jadi
     * index parsial memangkas scan compared to a full-table remaining check.
     */
    public function up(): void
    {
        Schema::table('supplier_debit_memos', function (Blueprint $table) {
            $table->index(['supplier_id', 'branch_id'], 'sdm_open_supplier_branch_idx');
        });

        // Partial index via statement (Blueprint tidak punya partial index).
        DB::statement(
            'CREATE INDEX IF NOT EXISTS sdm_open_remaining_idx
             ON supplier_debit_memos (supplier_id, remaining)
             WHERE remaining > 0'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS sdm_open_remaining_idx');

        Schema::table('supplier_debit_memos', function (Blueprint $table) {
            $table->dropIndex('sdm_open_supplier_branch_idx');
        });
    }
};
