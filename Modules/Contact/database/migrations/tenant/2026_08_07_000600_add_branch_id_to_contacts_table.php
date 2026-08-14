<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->unsignedBigInteger('branch_id')->nullable()->after('id');
            $table->index('branch_id');
        });

        // Backfill existing contacts to the tenant's headquarters branch (or the
        // first active branch) instead of discarding them. No data is deleted.
        $defaultBranchId = DB::table('branches')
            ->where('is_active', true)
            ->orderByDesc('is_headquarters')
            ->orderBy('id')
            ->value('id');

        if ($defaultBranchId !== null) {
            DB::table('contacts')->whereNull('branch_id')->update(['branch_id' => $defaultBranchId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
