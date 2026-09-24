<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_invoice_items', 'qty_returned')) {
                $table->decimal('qty_returned', 15, 4)->default(0)->after('qty');
            }
        });

        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (! Schema::hasColumn('purchase_invoices', 'returned_amount')) {
                $table->decimal('returned_amount', 15, 4)->default(0)->after('total');
            }

            if (! Schema::hasColumn('purchase_invoices', 'paid_amount')) {
                $table->decimal('paid_amount', 15, 4)->default(0)->after('returned_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_invoices', 'paid_amount')) {
                $table->dropColumn('paid_amount');
            }

            if (Schema::hasColumn('purchase_invoices', 'returned_amount')) {
                $table->dropColumn('returned_amount');
            }
        });

        Schema::table('purchase_invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('purchase_invoice_items', 'qty_returned')) {
                $table->dropColumn('qty_returned');
            }
        });
    }
};
