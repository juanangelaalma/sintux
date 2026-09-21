<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nama customer di sistem supplier (mis. "AII PALEMBANG") untuk
     * memetakan DO supplier ke cabang internal saat fetch penerimaan.
     */
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'supplier_customer_name')) {
                $table->string('supplier_customer_name', 255)->nullable()->unique();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'supplier_customer_name')) {
                $table->dropUnique(['supplier_customer_name']);
                $table->dropColumn('supplier_customer_name');
            }
        });
    }
};
