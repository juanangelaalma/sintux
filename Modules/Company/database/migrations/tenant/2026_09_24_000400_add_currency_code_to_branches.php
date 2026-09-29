<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mata uang default per cabang. Perilaku v1 tetap single-currency:
     * pembayaran mengikuti cabang, tanpa konversi/kurs.
     */
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'currency_code')) {
                $table->string('currency_code', 3)->default('IDR')->after('code');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('branches')) {
            return;
        }

        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'currency_code')) {
                $table->dropColumn('currency_code');
            }
        });
    }
};
