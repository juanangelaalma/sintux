<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Direct transfers (inisiatif HO / antar branch) tidak memiliki
         * stock request, sehingga kolom ini harus nullable.
         *
         * Raw statement karena doctrine/dbal tidak terinstal
         * sehingga Blueprint::change() tidak tersedia.
         */
        DB::statement('ALTER TABLE stock_transfers ALTER COLUMN stock_request_id DROP NOT NULL');

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropColumn(['created_by', 'approved_by', 'approved_at']);
        });

        DB::statement('ALTER TABLE stock_transfers ALTER COLUMN stock_request_id SET NOT NULL');
    }
};
