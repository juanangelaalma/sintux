<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('branch_id')->after('id')->nullable()->constrained('branches');
        });

        // Backfill: assign all existing products to the first headquarters branch
        $hqBranch = DB::connection('tenant')
            ->table('branches')
            ->where('is_headquarters', true)
            ->first();

        if ($hqBranch) {
            DB::connection('tenant')
                ->table('products')
                ->whereNull('branch_id')
                ->update(['branch_id' => $hqBranch->id]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique(['branch_id', 'code'], 'products_branch_code_unique');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
