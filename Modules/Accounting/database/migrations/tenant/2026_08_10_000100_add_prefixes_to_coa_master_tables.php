<?php

declare(strict_types=1);

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
        Schema::table('coa_account_types', function (Blueprint $table) {
            $table->string('prefix', 10)->nullable()->after('code');
        });

        Schema::table('coa_account_categories', function (Blueprint $table) {
            $table->string('prefix', 10)->nullable()->after('code');
        });

        $typePrefixes = [
            'ASSET' => '1',
            'LIABILITY' => '2',
            'EQUITY' => '3',
            'REVENUE' => '4',
            'COST_OF_REVENUE' => '5',
            'EXPENSE' => '6',
        ];

        foreach ($typePrefixes as $code => $prefix) {
            DB::table('coa_account_types')->where('code', $code)->update(['prefix' => $prefix]);
        }

        $categoryPrefixes = [
            'CASH_AND_BANK' => '100',
            'RECEIVABLE' => '101',
            'INVENTORY' => '102',
            'OTHER_CURRENT_ASSET' => '103',
            'FIXED_ASSET' => '150',
            'CURRENT_LIABILITY' => '200',
            'TAX_LIABILITY' => '201',
            'LONG_TERM_LIABILITY' => '250',
            'EQUITY' => '300',
            'OPERATING_REVENUE' => '400',
            'OTHER_REVENUE' => '490',
            'COST_OF_REVENUE' => '500',
            'SELLING_EXPENSE' => '600',
            'GENERAL_ADMIN_EXPENSE' => '610',
            'PERSONNEL_EXPENSE' => '620',
            'DEPRECIATION_EXPENSE' => '630',
            'OTHER_EXPENSE' => '690',
        ];

        foreach ($categoryPrefixes as $code => $prefix) {
            DB::table('coa_account_categories')->where('code', $code)->update(['prefix' => $prefix]);
        }

        Schema::table('coa_account_types', function (Blueprint $table) {
            $table->unique('prefix', 'coa_account_types_prefix_unique');
        });

        Schema::table('coa_account_categories', function (Blueprint $table) {
            $table->unique(['account_type_id', 'prefix'], 'coa_categories_type_prefix_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coa_account_categories', function (Blueprint $table) {
            $table->dropUnique('coa_categories_type_prefix_unique');
            $table->dropColumn('prefix');
        });

        Schema::table('coa_account_types', function (Blueprint $table) {
            $table->dropUnique('coa_account_types_prefix_unique');
            $table->dropColumn('prefix');
        });
    }
};
