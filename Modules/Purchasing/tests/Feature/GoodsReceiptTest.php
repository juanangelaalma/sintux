<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Purchasing\Models\PurchaseOrder;
use Tests\TestCase;

class GoodsReceiptTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();
        $this->cleanupCentralTables();
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

        parent::tearDown();
    }

    public function test_post_goods_receipt_receives_stock_into_warehouse_and_updates_po_status(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-BRB-'.uniqid(),
            'name' => 'Branch B Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $po = PurchaseOrder::create([
            'number' => 'PO-BRB-0001',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => 'sent',
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $poItem = $po->items()->create([
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_ordered' => 20,
            'qty_received' => 0,
            'unit_price' => 50000,
        ]);

        $grn = GoodsReceipt::create([
            'number' => 'GRN-BRB-0001',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouseId,
            'status' => 'draft',
            'receipt_date' => now()->toDateString(),
        ]);
        $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_received' => 20,
        ]);
        $grnId = $grn->id;
        $poId = $po->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.grns.post', $grnId));
        $response->assertRedirect(route('purchasing.grns.show', $grnId));

        tenancy()->initialize($tenantId);
        // 1. GRN status posted
        $this->assertSame('posted', DB::table('goods_receipts')->where('id', $grnId)->value('status'));

        // 2. Stock balance in Warehouse updated
        $stockBalance = DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->first();
        $this->assertNotNull($stockBalance);
        $this->assertEquals(20, (float) $stockBalance->qty_on_hand);

        // 3. Stock Movement recorded
        $movement = DB::table('stock_movements')
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->where('movement_type', 'purchase_in')
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(20, (float) $movement->qty);

        // 4. PO item qty_received updated & PO status received
        $poItemDb = DB::table('purchase_order_items')->where('id', $poItem->id)->first();
        $this->assertEquals(20, (float) $poItemDb->qty_received);

        $poDb = DB::table('purchase_orders')->where('id', $poId)->first();
        $this->assertSame('received', $poDb->status);
        tenancy()->end();
    }

    public function test_store_goods_receipt_via_http_creates_draft_grn_with_items(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant('PRD-002', true);
        $supplierId = $this->createSupplier($branchBId);
        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-STORE-'.uniqid(),
            'name' => 'Store Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $po = PurchaseOrder::create([
            'number' => 'PO-STORE-'.uniqid(),
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => 'sent',
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $poItem = $po->items()->create([
            'product_variant_id' => $variantId,
            'product_name' => 'Widget Store',
            'sku' => 'SKU-STORE',
            'qty_ordered' => 15,
            'qty_received' => 0,
            'unit_price' => 25000,
        ]);

        $payload = [
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouseId,
            'receipt_date' => now()->toDateString(),
            'note' => 'Diterima via HTTP form',
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_variant_id' => $variantId,
                    'product_name' => 'Widget Store',
                    'sku' => 'SKU-STORE',
                    'qty_received' => 10,
                    'unit_price' => 25000,
                ],
            ],
        ];

        $poId = $po->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.grns.store'), $payload);
        $response->assertSessionHasNoErrors();

        tenancy()->initialize($tenantId);
        $grn = DB::table('goods_receipts')
            ->where('purchase_order_id', $poId)
            ->first();
        $this->assertNotNull($grn, 'GRN harus dibuat dari form');
        $this->assertSame('draft', $grn->status);
        $this->assertEquals($branchBId, $grn->branch_id);
        $this->assertEquals($supplierId, $grn->supplier_id);
        $this->assertEquals($warehouseId, $grn->warehouse_id);

        $grnItem = DB::table('goods_receipt_items')
            ->where('goods_receipt_id', $grn->id)
            ->first();
        $this->assertNotNull($grnItem);
        $this->assertEquals($poItem->id, $grnItem->purchase_order_item_id);
        $this->assertEquals(10, (float) $grnItem->qty_received);

        // PO item belum berubah sebelum GRN diposting
        $this->assertEquals(0, (float) DB::table('purchase_order_items')
            ->where('id', $poItem->id)
            ->value('qty_received'));
        tenancy()->end();

        $this->assertSame(
            route('purchasing.grns.index'),
            $response->headers->get('Location')
        );
    }

    /**
     * @return array{0: string|int, 1: int, 2: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('grn_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'GRN Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $existingHq = DB::table('branches')->where('code', 'HQ')->value('id');
        $hqBranchId = $existingHq
            ? (int) $existingHq
            : DB::table('branches')->insertGetId([
                'name' => 'HQ Branch',
                'code' => 'HQ',
                'is_headquarters' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_'.uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'member_'.$id.'@acme.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'is_default' => true,
        ]);

        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $hqBranchId,
        ]);
        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $branchBId,
        ]);

        return [$tenant->id, (int) $branchBId, $user];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createProductAndVariant(string $codePrefix = 'PRD', bool $variantActive = true): array
    {
        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Category '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS '.uniqid(),
            'code' => 'PCS'.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'code' => $codePrefix.'-'.uniqid(),
            'name' => 'Widget '.uniqid(),
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'SKU-'.uniqid(),
            'variant_name' => 'Widget Variant '.uniqid(),
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => $variantActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$variantId, $productId];
    }

    private function createSupplier(int $branchId): int
    {
        return (int) DB::table('contacts')->insertGetId([
            'branch_id' => $branchId,
            'type' => 'supplier',
            'name' => 'Supplier '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanupCentralTables(): void
    {
        $this->deleteIfTableExists('goods_receipt_items');
        $this->deleteIfTableExists('goods_receipts');
        $this->deleteIfTableExists('purchase_order_items');
        $this->deleteIfTableExists('purchase_orders');
        $this->deleteIfTableExists('company_user_branches');
        $this->deleteIfTableExists('company_users');
        $this->deleteIfTableExists('tenants');
        $this->deleteIfTableExists('users');
    }

    private function deleteIfTableExists(string $table): void
    {
        $exists = DB::selectOne(
            'SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = current_schema()
                AND table_name = ?
            ) AS exists',
            [$table]
        );

        if ($exists && (bool) $exists->exists) {
            DB::table($table)->delete();
        }
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            $safeSchemaName = str_replace('"', '""', $schemaName);
            DB::statement('DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE');
        } catch (\Throwable $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name
                 FROM information_schema.schemata
                 WHERE schema_name NOT IN ('public', 'information_schema')
                 AND schema_name NOT LIKE 'pg_%'"
            );

            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;
                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
