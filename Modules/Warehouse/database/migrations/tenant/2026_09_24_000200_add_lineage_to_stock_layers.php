<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lineage stok: tiap layer tahu PO akarnya (root_*) dan layer asal
     * saat dipindah via transfer (parent_layer_id). Dipakai untuk
     * timeline rantai PO → GRN → transfer → retur dan validasi PO-sama.
     */
    public function up(): void
    {
        Schema::table('stock_layers', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_layers', 'root_source_type')) {
                $table->string('root_source_type')->nullable()->after('source_id');
            }

            if (! Schema::hasColumn('stock_layers', 'root_source_id')) {
                $table->unsignedBigInteger('root_source_id')->nullable()->after('root_source_type');
            }

            if (! Schema::hasColumn('stock_layers', 'parent_layer_id')) {
                $table->foreignId('parent_layer_id')->nullable()->after('root_source_id')
                    ->constrained('stock_layers')->nullOnDelete();
            }
        });

        if (! Schema::hasIndex('stock_layers', ['root_source_type', 'root_source_id'])) {
            Schema::table('stock_layers', function (Blueprint $table) {
                $table->index(['root_source_type', 'root_source_id']);
            });
        }

        // Backfill: layer lama tidak punya riwayat parent, hanya root
        // sejauh yang bisa dibuktikan dari (source_type, source_id).
        DB::statement('
            UPDATE stock_layers
            SET root_source_type = source_type,
                root_source_id = source_id
            WHERE root_source_type IS NULL AND source_type IS NOT NULL
        ');
    }

    public function down(): void
    {
        Schema::table('stock_layers', function (Blueprint $table) {
            if (Schema::hasColumn('stock_layers', 'parent_layer_id')) {
                $table->dropForeign(['parent_layer_id']);
                $table->dropColumn('parent_layer_id');
            }
        });

        if (Schema::hasIndex('stock_layers', ['root_source_type', 'root_source_id'])) {
            Schema::table('stock_layers', function (Blueprint $table) {
                $table->dropIndex(['root_source_type', 'root_source_id']);
            });
        }

        Schema::table('stock_layers', function (Blueprint $table) {
            foreach (['root_source_id', 'root_source_type'] as $column) {
                if (Schema::hasColumn('stock_layers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
