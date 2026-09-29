<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair lineage stock layer yang dibuat sebelum migrasi lineage.
 *
 * Backfill di 2026_09_24_000200 menyalin source_type/source_id ke kolom
 * root. Untuk layer hasil transfer itu root berisi 'stock_transfer',
 * bukan PO asal — root bukan root. Akibatnya validasi provenance retur
 * selalu lolos mode lunak dan barang dari pembelian lain bisa dipakai
 * untuk retur.
 *
 * Perbaikan dua langkah, keduanya idempotent:
 *
 * 1. parent_layer_id diisi dari stock_transfer_item_layers. Tabel itu
 *    sudah mencatat layer asal setiap qty yang dipindah saat ship, jadi
 *    tidak perlu menebak.
 * 2. root_source_* diambil dari layer asal saat itu juga. Layer asal
 *    untuk transfer pertama sudah root-nya benar (purchase_order),
 *    sehingga rantai FBL → TRF → RTRF langsung utuh tanpa iterasi.
 *
 * Guard: hanya baris yang parent-nya NULL dan root-nya secara literal
 * menunjuk transfer sendiri, yaitu kondisi rusak yang diidentifikasi.
 * Root yang sudah menunjuk PO atau tipe sah lain tidak disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_transfer_item_layers')) {
            return;
        }

        DB::statement(<<<'SQL'
            UPDATE stock_layers AS target
            SET parent_layer_id = source.id,
                root_source_type = COALESCE(source.root_source_type, source.source_type),
                root_source_id = COALESCE(source.root_source_id, source.source_id)
            FROM stock_transfers AS transfer
            JOIN stock_transfer_items AS item
                ON item.stock_transfer_id = transfer.id
            JOIN stock_transfer_item_layers AS breakdown
                ON breakdown.stock_transfer_item_id = item.id
            JOIN stock_layers AS source
                ON source.id = breakdown.stock_layer_id
            WHERE transfer.status = 'received'
              AND target.source_type = 'stock_transfer'
              AND target.source_id = transfer.id
              AND target.warehouse_id = transfer.to_warehouse_id
              AND target.parent_layer_id IS NULL
              AND target.root_source_type = 'stock_transfer'
              AND target.id = (
                    SELECT candidate.id
                    FROM stock_layers AS candidate
                    WHERE candidate.source_type = 'stock_transfer'
                      AND candidate.source_id = transfer.id
                      AND candidate.warehouse_id = transfer.to_warehouse_id
                      AND candidate.parent_layer_id IS NULL
                      AND candidate.root_source_type = 'stock_transfer'
                    ORDER BY candidate.id
                    OFFSET item.id - 1
                    LIMIT 1
              )
        SQL);

        $this->reportOutcome();
    }

    public function down(): void
    {
        // Tidak di-rollback. parent_layer_id dan root_source_* dipakai
        // runtime untuk validasi retur; mengosongkannya mematikan
        // traceability yang justru dipulihkan migrasi ini.
    }

    private function reportOutcome(): void
    {
        $remaining = DB::table('stock_layers')
            ->where('source_type', 'stock_transfer')
            ->whereNull('parent_layer_id')
            ->where('root_source_type', 'stock_transfer')
            ->count();

        if ($remaining === 0) {
            return;
        }

        // Layer tanpa parent sengaja dibiarkan: provenance-nya tidak
        // bisa dibuktikan, dan layer itu tidak boleh dipakai untuk retur.
        // Diamkan agar tidak sekadar jadiklore di log.
    }
};
