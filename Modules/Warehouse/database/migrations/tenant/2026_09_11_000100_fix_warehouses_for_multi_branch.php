<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Warehouse\Application\Warehouse\CreateWarehousesForBranch;

return new class extends Migration
{
    /**
     * @var bool
     */
    public $withinTransaction = false;

    public function up(): void
    {
        // 1. Normalize existing 'general' to 'regular' before check change
        DB::table('warehouses')->where('warehouse_type', 'general')->update(['warehouse_type' => 'regular']);

        // 2. Update check constraint to allow 'retail' (Postgres enum is implemented as check)
        try {
            DB::statement('ALTER TABLE warehouses DROP CONSTRAINT IF EXISTS warehouses_warehouse_type_check');
        } catch (Throwable $e) {
        }
        try {
            DB::statement("ALTER TABLE warehouses ADD CONSTRAINT warehouses_warehouse_type_check CHECK (warehouse_type IN ('regular','retail','consignment'))");
        } catch (Throwable $e) {
            // Fallback: try via Schema for SQLite
            try {
                Schema::table('warehouses', function (Blueprint $table) {
                    $table->string('warehouse_type')->default('regular')->change();
                });
            } catch (Throwable $e2) {
            }
        }

        // 3. Ensure code uniqueness and branch_type uniqueness
        // Note: code unique already exists from create_warehouses_table, keep it
        // Add unique (branch_id, warehouse_type) if not exists
        $hasUnique = false;
        try {
            $indexes = DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'warehouses'");
            foreach ($indexes as $idx) {
                if (str_contains($idx->indexname, 'warehouses_branch_id_warehouse_type_unique')) {
                    $hasUnique = true;
                    break;
                }
            }
        } catch (Throwable $e) {
        }

        if (! $hasUnique) {
            Schema::table('warehouses', function (Blueprint $table) {
                $table->unique(['branch_id', 'warehouse_type'], 'warehouses_branch_id_warehouse_type_unique');
            });
        }

        // 4. Ensure each branch has 3 system warehouses (REG/RIT/KON)
        if (Schema::hasTable('branches') && Schema::hasTable('warehouses')) {
            $branches = DB::table('branches')->get(['id', 'code', 'name']);
            foreach ($branches as $branch) {
                try {
                    app(CreateWarehousesForBranch::class)->execute(
                        (int) $branch->id,
                        (string) $branch->code,
                        (string) $branch->name
                    );
                } catch (Throwable $e) {
                    // Ignore seeding errors during migration (e.g., branch without code)
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            try {
                $table->dropUnique('warehouses_branch_id_warehouse_type_unique');
            } catch (Throwable $e) {
            }
        });
        // Enum value removal not supported in Postgres without recreate, leave 'retail' in type
    }
};
