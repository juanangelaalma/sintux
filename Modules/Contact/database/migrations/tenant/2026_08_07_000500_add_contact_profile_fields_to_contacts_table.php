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
            $table->dropColumn('phone');

            $table->date('registered_at')->default(DB::raw('CURRENT_DATE'));
            $table->string('tier_relation', 1)->nullable();
            $table->string('identity_type', 20)->nullable();
            $table->string('identity_number', 50)->nullable();
            $table->string('company_name')->nullable();
            $table->string('mobile_phone', 50)->nullable();
            $table->string('telephone', 50)->nullable();
            $table->string('fax', 50)->nullable();
            $table->string('npwp', 30)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number', 50)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'registered_at',
                'tier_relation',
                'identity_type',
                'identity_number',
                'company_name',
                'mobile_phone',
                'telephone',
                'fax',
                'npwp',
                'bank_name',
                'bank_branch',
                'bank_account_name',
                'bank_account_number',
            ]);

            $table->string('phone')->nullable();
        });
    }
};
