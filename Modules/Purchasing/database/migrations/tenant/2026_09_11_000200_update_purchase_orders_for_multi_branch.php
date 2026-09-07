<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop order_type from purchase_orders if exists
        if (Schema::hasColumn('purchase_orders', 'order_type')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('order_type');
            });
        }

        // 2. Drop order_type from purchase_order_items if still exists (legacy)
        if (Schema::hasColumn('purchase_order_items', 'order_type')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropColumn('order_type');
            });
        }

        // 3. Add branch_mode to purchase_orders
        if (! Schema::hasColumn('purchase_orders', 'branch_mode')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('branch_mode', 20)->default('single')->after('warehouse_id');
            });
        }

        // 4. Add destination_branch_id and destination_warehouse_id to purchase_order_items
        if (! Schema::hasColumn('purchase_order_items', 'destination_branch_id')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->foreignId('destination_branch_id')->after('purchase_order_id')->constrained('branches');
            });

            // Backfill for existing rows: destination = header branch_id, dest warehouse = header warehouse_id
            // Only for migrate without fresh (if data exists)
            $items = DB::table('purchase_order_items')->get(['id', 'purchase_order_id']);
            foreach ($items as $item) {
                $po = DB::table('purchase_orders')->where('id', $item->purchase_order_id)->first(['branch_id', 'warehouse_id']);
                if ($po) {
                    DB::table('purchase_order_items')->where('id', $item->id)->update([
                        'destination_branch_id' => $po->branch_id,
                        'destination_warehouse_id' => $po->warehouse_id,
                    ]);
                }
            }

            // Make destination_warehouse_id NOT NULL after backfill? Keep nullable for flexibility, but spec says wajib regular
            // We will add column as nullable then enforce in application, keep nullable false for destination_branch_id already via foreignId default NOT NULL
        }

        if (! Schema::hasColumn('purchase_order_items', 'destination_warehouse_id')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->foreignId('destination_warehouse_id')->nullable()->after('destination_branch_id')->constrained('warehouses');
            });

            // Backfill warehouse if still null (already done above, but ensure)
            $items = DB::table('purchase_order_items')->whereNull('destination_warehouse_id')->get(['id', 'purchase_order_id']);
            foreach ($items as $item) {
                $po = DB::table('purchase_orders')->where('id', $item->purchase_order_id)->first(['warehouse_id']);
                if ($po && $po->warehouse_id) {
                    DB::table('purchase_order_items')->where('id', $item->id)->update([
                        'destination_warehouse_id' => $po->warehouse_id,
                    ]);
                }
            }
        }

        // 5. Ensure destination_warehouse_id is NOT NULL for future (keep nullable for now to allow manual, but enforce via validation)
        // We do not make it NOT NULL via migration to avoid breaking existing data without warehouse
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchase_order_items', 'destination_warehouse_id')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropForeign(['destination_warehouse_id']);
                $table->dropColumn('destination_warehouse_id');
            });
        }
        if (Schema::hasColumn('purchase_order_items', 'destination_branch_id')) {
            Schema::table('purchase_order_items', function (Blueprint $table) {
                $table->dropForeign(['destination_branch_id']);
                $table->dropColumn('destination_branch_id');
            });
        }
        if (Schema::hasColumn('purchase_orders', 'branch_mode')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->dropColumn('branch_mode');
            });
        }
        if (! Schema::hasColumn('purchase_orders', 'order_type')) {
            Schema::table('purchase_orders', function (Blueprint $table) {
                $table->string('order_type')->default('regular')->after('source_request_id');
            });
        }
    }
};
