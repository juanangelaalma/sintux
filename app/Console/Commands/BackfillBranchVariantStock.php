<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Product\Application\Product\EnsureVariantForBranch;

class BackfillBranchVariantStock extends Command
{
    protected $signature = 'warehouse:backfill-branch-variants
        {--tenant=* : Tenant ids to backfill. Defaults to all active tenants.}';

    protected $description = 'Re-point legacy cross-branch stock (recorded on the source-branch variant) to mirrored variants owned by each warehouse branch';

    public function handle(EnsureVariantForBranch $ensureVariantForBranch): int
    {
        $tenantIds = $this->option('tenant');

        $query = Tenant::query();

        if ($tenantIds !== []) {
            $query->whereIn('id', $tenantIds);
        } else {
            $query->where('is_active', true);
        }

        $tenantIdList = $query->pluck('id')->all();

        foreach ($tenantIdList as $tenantId) {
            tenancy()->initialize($tenantId);

            $this->components->twoColumnDetail('Tenant', $tenantId);

            $movedBalances = $this->migrateStockBalances($ensureVariantForBranch);
            $movedLayers = $this->migrateStockLayers($ensureVariantForBranch);
            $movedMovements = $this->migrateStockMovements($ensureVariantForBranch);

            $this->components->twoColumnDetail(
                'stock_balances moved',
                (string) $movedBalances
            );
            $this->components->twoColumnDetail(
                'stock_layers moved',
                (string) $movedLayers
            );
            $this->components->twoColumnDetail(
                'stock_movements moved',
                (string) $movedMovements
            );

            tenancy()->end();
        }

        $this->info('Backfill complete.');

        return self::SUCCESS;
    }

    private function migrateStockBalances(EnsureVariantForBranch $ensureVariantForBranch): int
    {
        $rows = DB::table('stock_balances')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->join('product_variants', 'product_variants.id', '=', 'stock_balances.product_variant_id')
            ->whereColumn('warehouses.branch_id', '!=', 'product_variants.branch_id')
            ->get([
                'stock_balances.id',
                'stock_balances.warehouse_id',
                'stock_balances.product_variant_id',
                'warehouses.branch_id',
            ]);

        $moved = 0;

        foreach ($rows as $row) {
            $branchVariantId = $ensureVariantForBranch->execute(
                (int) $row->product_variant_id,
                (int) $row->branch_id
            );

            if ($branchVariantId === (int) $row->product_variant_id) {
                continue;
            }

            $existing = DB::table('stock_balances')
                ->where('warehouse_id', $row->warehouse_id)
                ->where('product_variant_id', $branchVariantId)
                ->first();

            $legacyRow = DB::table('stock_balances')->where('id', $row->id)->first();

            if ($existing && $legacyRow) {
                DB::table('stock_balances')
                    ->where('id', $existing->id)
                    ->update([
                        'qty_on_hand' => (float) $existing->qty_on_hand + (float) $legacyRow->qty_on_hand,
                        'updated_at' => now(),
                    ]);

                DB::table('stock_balances')->where('id', $row->id)->delete();
            } elseif ($legacyRow) {
                DB::table('stock_balances')
                    ->where('id', $row->id)
                    ->update([
                        'product_variant_id' => $branchVariantId,
                        'updated_at' => now(),
                    ]);
            }

            $moved++;
        }

        return $moved;
    }

    private function migrateStockLayers(EnsureVariantForBranch $ensureVariantForBranch): int
    {
        $rows = DB::table('stock_layers')
            ->join('warehouses', 'warehouses.id', '=', 'stock_layers.warehouse_id')
            ->join('product_variants', 'product_variants.id', '=', 'stock_layers.product_variant_id')
            ->whereColumn('warehouses.branch_id', '!=', 'product_variants.branch_id')
            ->get([
                'stock_layers.id',
                'stock_layers.product_variant_id',
                'warehouses.branch_id',
            ]);

        $moved = 0;

        foreach ($rows as $row) {
            $branchVariantId = $ensureVariantForBranch->execute(
                (int) $row->product_variant_id,
                (int) $row->branch_id
            );

            if ($branchVariantId === (int) $row->product_variant_id) {
                continue;
            }

            DB::table('stock_layers')
                ->where('id', $row->id)
                ->update(['product_variant_id' => $branchVariantId]);

            $moved++;
        }

        return $moved;
    }

    private function migrateStockMovements(EnsureVariantForBranch $ensureVariantForBranch): int
    {
        $rows = DB::table('stock_movements')
            ->join('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->join('product_variants', 'product_variants.id', '=', 'stock_movements.product_variant_id')
            ->whereColumn('warehouses.branch_id', '!=', 'product_variants.branch_id')
            ->get([
                'stock_movements.id',
                'stock_movements.product_variant_id',
                'warehouses.branch_id',
            ]);

        $moved = 0;

        foreach ($rows as $row) {
            $branchVariantId = $ensureVariantForBranch->execute(
                (int) $row->product_variant_id,
                (int) $row->branch_id
            );

            if ($branchVariantId === (int) $row->product_variant_id) {
                continue;
            }

            DB::table('stock_movements')
                ->where('id', $row->id)
                ->update(['product_variant_id' => $branchVariantId]);

            $moved++;
        }

        return $moved;
    }
}
