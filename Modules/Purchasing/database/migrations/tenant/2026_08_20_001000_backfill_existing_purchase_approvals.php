<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Backfill existing purchase requests, purchase orders, and purchase invoices
     * created before the multilayer approval feature was added.
     *
     * Mark pending PRs, pending POs, and draft invoices as approved so legacy data is not stuck.
     */
    public function up(): void
    {
        DB::table('purchase_requests')
            ->where('status', 'pending')
            ->update(['status' => 'approved']);

        DB::table('purchase_orders')
            ->where('status', 'pending')
            ->update(['status' => 'approved']);

        DB::table('purchase_invoices')
            ->where('status', 'draft')
            ->update(['status' => 'approved']);
    }

    public function down(): void
    {
        // No-op for safety
    }
};
