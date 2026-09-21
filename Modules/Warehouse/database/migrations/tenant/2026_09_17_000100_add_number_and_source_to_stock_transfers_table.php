<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nomor dokumen administratif + tautan sumber (mis. penerimaan
     * cabang) untuk setiap stock transfer.
     */
    public function up(): void
    {
        if (! Schema::hasTable('stock_transfers')) {
            return;
        }

        Schema::table('stock_transfers', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_transfers', 'number')) {
                $table->string('number', 20)->nullable()->unique();
            }

            if (! Schema::hasColumn('stock_transfers', 'source_type')) {
                $table->string('source_type', 255)->nullable();
            }

            if (! Schema::hasColumn('stock_transfers', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable();
            }

            $table->index(['source_type', 'source_id']);
        });

        // Backfill nomor untuk transfer lama: TRF/YYYYMMDD/NNN dengan
        // NNN urut harian mengikuti urutan id.
        $sequenceByDate = [];
        DB::table('stock_transfers')
            ->whereNull('number')
            ->orderBy('id')
            ->chunk(200, function ($rows) use (&$sequenceByDate): void {
                foreach ($rows as $row) {
                    $day = substr((string) $row->created_at, 0, 10);
                    $sequenceByDate[$day] = ($sequenceByDate[$day] ?? 0) + 1;
                    DB::table('stock_transfers')
                        ->where('id', $row->id)
                        ->update([
                            'number' => sprintf(
                                'TRF/%s/%03d',
                                str_replace('-', '', $day),
                                $sequenceByDate[$day]
                            ),
                        ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_transfers')) {
            return;
        }

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropUnique(['number']);
            $table->dropColumn(['number', 'source_type', 'source_id']);
        });
    }
};
