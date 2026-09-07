<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchase_order_items', 'destination_expected_date')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->date('destination_expected_date')->nullable()->after('destination_warehouse_id');
            });
        }

        // Backfill existing items with header expected_date or order_date
        $items = DB::table('purchase_order_items')->whereNull('destination_expected_date')->get(['id', 'purchase_order_id']);
        foreach ($items as $item) {
            $po = DB::table('purchase_orders')->where('id', $item->purchase_order_id)->first(['order_date', 'expected_date', 'due_date']);
            if ($po) {
                $fallback = $po->expected_date ?? $po->due_date ?? $po->order_date ?? now()->toDateString();
                DB::table('purchase_order_items')->where('id', $item->id)->update([
                    'destination_expected_date' => $fallback,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_order_items', 'destination_expected_date')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('destination_expected_date');
            });
        }
    }
};
