<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->string('type', 10)->default('single')->after('rate');
            $table->boolean('is_withholding')->default(false)->after('type');
            $table->boolean('dpp_multiplier')->default(false)->after('is_withholding');
        });
    }

    public function down(): void
    {
        Schema::table('taxes', function (Blueprint $table) {
            $table->dropColumn(['type', 'is_withholding', 'dpp_multiplier']);
        });
    }
};
