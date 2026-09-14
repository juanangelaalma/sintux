<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class BackfillBranchVariantStockTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();

        DB::table('company_user_branches')->delete();
        DB::table('company_users')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
            $this->activeSchemaName = null;
        }

        $this->dropLeftoverSchemas();

        parent::tearDown();
    }

    /**
     * Legacy cross-branch receives (recorded on the source-branch variant)
     * are re-pointed to a mirrored variant owned by the warehouse's branch.
     */
    public function test_backfill_moves_legacy_cross_branch_stock_to_branch_variant(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user, $hqVariantId, $sku] =
            $this->seedTenantWithLegacyStock();

        Artisan::call('warehouse:backfill-branch-variants', [
            '--tenant' => [$tenantId],
        ]);

        tenancy()->initialize($tenantId);

        /*
         * Branch B now owns a mirrored product + variant.
         */
        $branchVariant = DB::table('product_variants')
            ->where('branch_id', $branchBId)
            ->where('sku', $sku)
            ->first();

        $this->assertNotNull($branchVariant, 'Mirrored variant was not created.');

        $branchProduct = DB::table('products')
            ->where('id', $branchVariant->product_id)
            ->first();

        $this->assertNotNull($branchProduct);
        $this->assertSame($branchBId, (int) $branchProduct->branch_id);

        /*
         * Stock in Branch B's warehouse moved to the mirrored variant.
         */
        $branchStock = DB::table('stock_balances')
            ->where('product_variant_id', $branchVariant->id)
            ->get();

        $this->assertCount(1, $branchStock);
        $this->assertSame(100, (int) $branchStock[0]->qty_on_hand);

        /*
         * No residual rows for the HQ variant in Branch B's warehouse.
         */
        $legacyRow = DB::table('stock_balances')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->where('stock_balances.product_variant_id', $hqVariantId)
            ->where('warehouses.branch_id', $branchBId)
            ->first();

        $this->assertNull($legacyRow);

        /*
         * Stock movements and layers in Branch B's warehouse re-pointed too.
         */
        $legacyMovement = DB::table('stock_movements')
            ->join('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')
            ->where('stock_movements.product_variant_id', $hqVariantId)
            ->where('warehouses.branch_id', $branchBId)
            ->first();

        $this->assertNull($legacyMovement);

        $branchMovement = DB::table('stock_movements')
            ->where('product_variant_id', $branchVariant->id)
            ->first();

        $this->assertNotNull($branchMovement);
        $this->assertSame(100, (int) $branchMovement->qty);

        tenancy()->end();
    }

    /**
     * Same-branch stock (HQ variant stocked in HQ warehouse) stays untouched.
     */
    public function test_backfill_leaves_same_branch_stock_untouched(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user, $hqVariantId, $sku] =
            $this->seedTenantWithLegacyStock();

        Artisan::call('warehouse:backfill-branch-variants', [
            '--tenant' => [$tenantId],
        ]);

        tenancy()->initialize($tenantId);

        $hqStock = DB::table('stock_balances')
            ->where('product_variant_id', $hqVariantId)
            ->get();

        $this->assertCount(1, $hqStock);
        $this->assertSame(300, (int) $hqStock[0]->qty_on_hand);

        /*
         * Idempotent: running again changes nothing.
         */
        Artisan::call('warehouse:backfill-branch-variants', [
            '--tenant' => [$tenantId],
        ]);

        tenancy()->initialize($tenantId);

        $hqStockAfter = DB::table('stock_balances')
            ->where('product_variant_id', $hqVariantId)
            ->get();

        $this->assertCount(1, $hqStockAfter);
        $this->assertSame(300, (int) $hqStockAfter[0]->qty_on_hand);

        $branchVariants = DB::table('product_variants')
            ->where('sku', $sku)
            ->count();

        $this->assertSame(2, $branchVariants);

        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: User, 4: int, 5: string}
     */
    private function seedTenantWithLegacyStock(): array
    {
        $id = uniqid('bf_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Backfill Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ_BF_'.$id,
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_BF_'.$id,
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hqWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-HQ-BF-'.$id,
            'name' => 'HQ Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-BRB-BF-'.$id,
            'name' => 'Branch B Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat '.$id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS '.$id,
            'code' => 'PCS'.$id,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'PRD-BF-'.$id,
            'name' => 'Legacy Widget '.$id,
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sku = 'SKU-BF-'.$id;
        $hqVariantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $hqBranchId,
            'product_id' => $productId,
            'sku' => $sku,
            'variant_name' => 'Legacy Variant',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Legacy state: HQ variant holds stock in BOTH branches' warehouses
         * (cross-branch receives recorded on the source variant).
         */
        DB::table('stock_balances')->insert([
            ['product_variant_id' => $hqVariantId, 'warehouse_id' => $hqWarehouseId, 'qty_on_hand' => 300, 'created_at' => now(), 'updated_at' => now()],
            ['product_variant_id' => $hqVariantId, 'warehouse_id' => $branchWarehouseId, 'qty_on_hand' => 100, 'created_at' => now(), 'updated_at' => now()],
        ]);

        DB::table('stock_layers')->insert([
            'product_variant_id' => $hqVariantId,
            'warehouse_id' => $branchWarehouseId,
            'qty_remaining' => 100,
            'unit_cost' => 100000,
            'received_at' => now(),
            'source_type' => 'stock_transfer',
            'source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_movements')->insert([
            'warehouse_id' => $branchWarehouseId,
            'product_variant_id' => $hqVariantId,
            'movement_type' => 'transfer_in',
            'qty' => 100,
            'unit_cost' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'owner_'.$id.'@acme.test',
            'role' => 'user',
        ]);

        CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'is_default' => true,
        ]);

        DB::table('company_user_branches')->insert([
            ['company_user_id' => CompanyUser::where('tenant_id', $tenant->id)->value('id'), 'branch_id' => $hqBranchId],
            ['company_user_id' => CompanyUser::where('tenant_id', $tenant->id)->value('id'), 'branch_id' => $branchBId],
        ]);

        return [$tenant->id, (int) $hqBranchId, (int) $branchBId, $user, (int) $hqVariantId, $sku];
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            DB::statement('DROP SCHEMA IF EXISTS "'.$schemaName.'" CASCADE');
        } catch (\Exception $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "
                SELECT schema_name
                FROM information_schema.schemata
                WHERE schema_name NOT IN (
                    'public',
                    'information_schema'
                )
                AND schema_name NOT LIKE 'pg_%'
                "
            );

            foreach ($schemas as $row) {
                if (str_starts_with($row->schema_name, 'sch_')) {
                    $this->dropSchema($row->schema_name);
                }
            }
        } catch (\Exception $e) {
        }
    }
}
