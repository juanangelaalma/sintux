<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class StockAdjustmentTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();

        DB::table('stock_movements')->delete();
        DB::table('stock_layers')->delete();
        DB::table('stock_balances')->delete();
        DB::table('stock_adjustment_items')->delete();
        DB::table('stock_adjustments')->delete();
        DB::table('company_user_branches')->delete();
        DB::table('company_users')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();

        $this->seed(\Modules\Company\Database\Seeders\RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        DB::table('stock_adjustment_items')->delete();
        DB::table('stock_adjustments')->delete();

        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
            $this->activeSchemaName = null;
        }

        $this->dropLeftoverSchemas();

        parent::tearDown();
    }

    public function test_user_can_create_stock_adjustment_in_draft(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);
        [$warehouseId, $variantId] = $this->seedWarehouseAndVariant($branchId);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('warehouse.adjustments.store'), [
            'warehouse_id' => $warehouseId,
            'type' => 'in',
            'note' => 'Initial stock opname',
            'items' => [
                [
                    'product_variant_id' => $variantId,
                    'qty' => 10,
                    'unit_cost' => 50000,
                    'note' => 'Box 1',
                ],
            ],
        ]);

        $response->assertRedirect();

        tenancy()->initialize($tenantId);
        $adjustment = DB::table('stock_adjustments')->where('warehouse_id', $warehouseId)->first();
        $this->assertNotNull($adjustment);
        $this->assertSame('in', $adjustment->type);
        $this->assertSame('draft', $adjustment->status);
        $this->assertStringStartsWith('ADJ-IN-', $adjustment->adjustment_number);

        $item = DB::table('stock_adjustment_items')->where('stock_adjustment_id', $adjustment->id)->first();
        $this->assertNotNull($item);
        $this->assertSame($variantId, (int) $item->product_variant_id);
        $this->assertEquals(10, (float) $item->qty);
        $this->assertEquals(50000, (float) $item->unit_cost);
        tenancy()->end();
    }

    public function test_post_stock_adjustment_in_creates_layer_and_updates_balance(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);
        [$warehouseId, $variantId] = $this->seedWarehouseAndVariant($branchId);
        tenancy()->end();

        // 1. Create draft IN adjustment
        $this->actingAs($user)->post(route('warehouse.adjustments.store'), [
            'warehouse_id' => $warehouseId,
            'type' => 'in',
            'note' => 'Stock IN test',
            'items' => [
                [
                    'product_variant_id' => $variantId,
                    'qty' => 15,
                    'unit_cost' => 100000,
                ],
            ],
        ]);

        tenancy()->initialize($tenantId);
        $adjustment = DB::table('stock_adjustments')->where('warehouse_id', $warehouseId)->first();
        tenancy()->end();

        // 2. Post the IN adjustment
        $response = $this->actingAs($user)->post(route('warehouse.adjustments.post', $adjustment->id));
        $response->assertRedirect();

        tenancy()->initialize($tenantId);

        // Assert adjustment status updated
        $postedAdj = DB::table('stock_adjustments')->where('id', $adjustment->id)->first();
        $this->assertSame('posted', $postedAdj->status);
        $this->assertNotNull($postedAdj->adjusted_at);

        // Assert Stock Layer created
        $layer = DB::table('stock_layers')
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->first();
        $this->assertNotNull($layer);
        $this->assertEquals(15, (float) $layer->qty_remaining);
        $this->assertEquals(100000, (float) $layer->unit_cost);

        // Assert Stock Balance updated
        $balance = DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->first();
        $this->assertNotNull($balance);
        $this->assertEquals(15, (float) $balance->qty_on_hand);

        // Assert Stock Movement recorded
        $movement = DB::table('stock_movements')
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->where('movement_type', 'adjustment_in')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(15, (float) $movement->qty);
        $this->assertEquals(100000, (float) $movement->unit_cost);

        tenancy()->end();
    }

    public function test_post_stock_adjustment_out_consumes_fifo_and_decrements_balance(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);
        [$warehouseId, $variantId] = $this->seedWarehouseAndVariant($branchId);

        // Seed an initial stock layer of 10 items
        $layerId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'qty_remaining' => 10,
            'unit_cost' => 50000,
            'received_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_balances')->insert([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty_on_hand' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        // 1. Create draft OUT adjustment for 4 items
        $this->actingAs($user)->post(route('warehouse.adjustments.store'), [
            'warehouse_id' => $warehouseId,
            'type' => 'out',
            'note' => 'Damaged items removal',
            'items' => [
                [
                    'product_variant_id' => $variantId,
                    'qty' => 4,
                ],
            ],
        ]);

        tenancy()->initialize($tenantId);
        $adjustment = DB::table('stock_adjustments')->where('warehouse_id', $warehouseId)->first();
        tenancy()->end();

        // 2. Post the OUT adjustment
        $response = $this->actingAs($user)->post(route('warehouse.adjustments.post', $adjustment->id));
        $response->assertRedirect();

        tenancy()->initialize($tenantId);

        // Assert layer remaining qty decremented (10 - 4 = 6)
        $layer = DB::table('stock_layers')->where('id', $layerId)->first();
        $this->assertEquals(6, (float) $layer->qty_remaining);

        // Assert balance decremented (10 - 4 = 6)
        $balance = DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->first();
        $this->assertEquals(6, (float) $balance->qty_on_hand);

        // Assert movement recorded (-4)
        $movement = DB::table('stock_movements')
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->where('movement_type', 'adjustment_out')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(-4, (float) $movement->qty);

        tenancy()->end();
    }

    private function createCompanyWithMember(): array
    {
        $id = uniqid('adj_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Adjustment Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        [$branchId, $member] = $this->provision($tenant, 'adj_member_'.$id.'@acme.test');

        return [$tenant->id, $branchId, $member];
    }

    private function provision(Tenant $tenant, string $email): array
    {
        tenancy()->initialize($tenant);
        $existingHq = DB::table('branches')->where('code', 'HQ')->value('id');
        $branchId = $existingHq ? (int) $existingHq : DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
        ]);
        tenancy()->end();

        $user = User::factory()->create(['email' => $email, 'role' => 'user']);

        CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'role' => 'member',
            'is_default' => true,
        ]);

        return [$branchId, $user];
    }

    private function seedWarehouseAndVariant(int $branchId): array
    {
        $suffix = uniqid();

        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'WH-'.$suffix,
            'name' => 'Main Warehouse '.$suffix,
            'warehouse_type' => 'general',
            'is_active' => true,
        ]);

        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Category '.$suffix, 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'Pcs '.$suffix, 'code' => 'PCS-'.$suffix, 'is_active' => true]);

        $productId = DB::table('products')->insertGetId([
            'code' => 'PROD-'.$suffix,
            'name' => 'Product '.$suffix,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'is_active' => true,
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'SKU-'.$suffix,
            'variant_name' => 'Default Variant '.$suffix,
            'is_active' => true,
        ]);

        return [$warehouseId, $variantId];
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
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('public', 'information_schema') AND schema_name NOT LIKE 'pg_%'"
            );
            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;
                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Exception $e) {
        }
    }
}
