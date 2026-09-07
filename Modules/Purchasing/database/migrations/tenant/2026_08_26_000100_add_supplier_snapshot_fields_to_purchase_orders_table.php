<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('supplier_email')->nullable()->after('supplier_id');
            $table->string('supplier_reference', 100)->nullable()->after('supplier_email');
            $table->string('billing_address', 500)->nullable()->after('supplier_reference');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['supplier_email', 'supplier_reference', 'billing_address']);
        });
    }
};
