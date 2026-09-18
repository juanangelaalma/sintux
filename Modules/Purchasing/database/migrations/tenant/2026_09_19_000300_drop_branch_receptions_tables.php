<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dokumen RCV dilebur ke GRN (fresh, tanpa backfill). Urutan drop
     * mengikuti arah foreign key: details → lines → header.
     */
    public function up(): void
    {
        Schema::dropIfExists('branch_reception_details');
        Schema::dropIfExists('branch_reception_lines');
        Schema::dropIfExists('branch_receptions');
    }

    public function down(): void
    {
        // Sengaja tidak dipulihkan: struktur RCV sudah digantikan GRN tunggal.
    }
};
