<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alasan penolakan HO agar cabang tahu apa yang harus direvisi.
     * Dipertahankan sebagai audit walau penerimaan sudah direvisi dan
     * disubmit ulang.
     */
    public function up(): void
    {
        Schema::table('branch_receptions', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('note');
        });
    }

    public function down(): void
    {
        Schema::table('branch_receptions', function (Blueprint $table) {
            $table->dropColumn('rejection_reason');
        });
    }
};
