<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Koreksi pencocokan parent pada repair lineage.
 *
 * Repair sebelumnya memakai ID item global sebagai ordinal transfer.
 * ID item tidak selalu berurutan dalam satu transfer, sehingga baris
 * bisa tidak tertaut atau tertaut ke layer asal yang salah.
 *
 * Koreksi ini memasangkan item-layer ke layer hasil receive berdasarkan
 * urutan di dalam transfer yang sama, lalu menurunkan root terminal dari
 * rantai parent. Baris yang sudah tepat dilewati sehingga aman diulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_transfer_item_layers')) {
            return;
        }

        $transfers = DB::table('stock_transfers')
            ->where('status', 'received')
            ->orderBy('id')
            ->get(['id', 'to_warehouse_id']);

        foreach ($transfers as $transfer) {
            $this->repairTransfer((int) $transfer->id, (int) $transfer->to_warehouse_id);
        }
    }

    public function down(): void
    {
        // Tidak di-rollback. Parent dan root hasil koreksi dipakai runtime
        // untuk validasi retur pembelian.
    }

    private function repairTransfer(int $transferId, int $warehouseId): void
    {
        $breakdowns = DB::table('stock_transfer_items as item')
            ->join(
                'stock_transfer_item_layers as breakdown',
                'breakdown.stock_transfer_item_id',
                '=',
                'item.id'
            )
            ->where('item.stock_transfer_id', $transferId)
            ->orderBy('breakdown.id')
            ->get(['breakdown.stock_layer_id']);

        if ($breakdowns->isEmpty()) {
            return;
        }

        $targets = DB::table('stock_layers')
            ->where('source_type', 'stock_transfer')
            ->where('source_id', $transferId)
            ->where('warehouse_id', $warehouseId)
            ->orderBy('id')
            ->get(['id', 'parent_layer_id', 'root_source_type', 'root_source_id']);

        foreach ($breakdowns as $position => $breakdown) {
            $target = $targets->get($position);

            if (! $target) {
                break;
            }

            $root = $this->terminalRoot((int) $breakdown->stock_layer_id);

            if ($root === null) {
                continue;
            }

            if ((int) $target->parent_layer_id === $root['parent_layer_id']
                && (string) $target->root_source_type === (string) $root['root_source_type']
                && (int) $target->root_source_id === (int) $root['root_source_id']
            ) {
                continue;
            }

            DB::table('stock_layers')->where('id', $target->id)->update([
                'parent_layer_id' => $root['parent_layer_id'],
                'root_source_type' => $root['root_source_type'],
                'root_source_id' => $root['root_source_id'],
            ]);
        }
    }

    /**
     * @return array{parent_layer_id: int, root_source_type: string|null, root_source_id: int|null}|null
     */
    private function terminalRoot(int $originLayerId): ?array
    {
        $seen = [];
        $current = DB::table('stock_layers')->find($originLayerId);

        for ($depth = 0; $depth < 10 && $current && ! isset($seen[(int) $current->id]); $depth++) {
            $seen[(int) $current->id] = true;

            if ($current->parent_layer_id === null) {
                return [
                    'parent_layer_id' => (int) $current->id,
                    'root_source_type' => $current->root_source_type,
                    'root_source_id' => $current->root_source_id !== null ? (int) $current->root_source_id : null,
                ];
            }

            $current = DB::table('stock_layers')->find($current->parent_layer_id);
        }

        return null;
    }
};
