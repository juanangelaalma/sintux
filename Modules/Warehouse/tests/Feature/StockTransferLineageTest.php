<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

/**
 * Panel lineage di detail transfer: asal pembelian (FBL) + layer asal
 * per item, supaya HO bisa menelusuri distribusi barang dari faktur.
 */
class StockTransferLineageTest extends TestCase
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

    public function test_show_includes_invoice_and_item_origins_for_grn_sourced_transfer(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);
        $transferId = $this->seedTransfer($ctx, [
            'source_type' => 'Modules\\Purchasing\\Models\\GoodsReceipt',
            'source_id' => $ctx['grnId'],
        ]);
        tenancy()->end();

        session([
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $ctx['hqBranchId'],
        ]);

        $this->actingAs($ctx['user'])
            ->get(route('warehouse.stock-transfers.show', $transferId))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('purchaseLineage.invoice.number', 'FBL/HQ/20260923/001/A')
                ->where('purchaseLineage.invoice.status', 'approved')
                ->has('purchaseLineage.items', 1)
                ->where('purchaseLineage.items.0.origins.0.layer_id', $ctx['originLayerId'])
                ->where('purchaseLineage.items.0.origins.0.warehouse_name', 'HQ Regular')
                ->where('purchaseLineage.items.0.origins.0.qty_taken', 6)
                ->where('purchaseLineage.items.0.origins.0.root_source_type', 'purchase_order')
                ->where('purchaseLineage.items.0.origins.0.root_source_id', $ctx['poId']));
    }

    public function test_show_has_null_invoice_when_transfer_has_no_grn_source(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);
        $transferId = $this->seedTransfer($ctx, [
            'source_type' => null,
            'source_id' => null,
        ]);
        tenancy()->end();

        session([
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $ctx['hqBranchId'],
        ]);

        $this->actingAs($ctx['user'])
            ->get(route('warehouse.stock-transfers.show', $transferId))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('purchaseLineage.invoice', null)
                ->has('purchaseLineage.items', 1)
                ->has('purchaseLineage.items.0.origins', 1));
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @param  array{source_type?: string|null, source_id?: int|null}  $source
     */
    private function seedTransfer(array $ctx, array $source): int
    {
        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => null,
            'from_warehouse_id' => $ctx['hqWarehouseId'],
            'to_warehouse_id' => $ctx['branchWarehouseId'],
            'number' => 'TRF-LIN-'.substr(uniqid(), -10),
            'status' => 'received',
            'source_type' => $source['source_type'] ?? null,
            'source_id' => $source['source_id'] ?? null,
            'created_by' => $ctx['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $itemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $ctx['variantId'],
            'qty' => 6,
            'qty_shipped' => 6,
            'qty_received' => 6,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_transfer_item_layers')->insert([
            'stock_transfer_item_id' => $itemId,
            'stock_layer_id' => $ctx['originLayerId'],
            'qty_taken' => 6,
            'unit_cost' => 47500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) $transferId;
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('stlin_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Transfer Lineage Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);
        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ', 'code' => 'HQ_'.uniqid(),
            'is_headquarters' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'name' => 'AII', 'code' => 'AII_'.uniqid(),
            'is_headquarters' => false, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $hqWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId, 'code' => 'WH-HQ-'.uniqid(),
            'name' => 'HQ Regular', 'warehouse_type' => 'regular',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId, 'code' => 'WH-AII-'.uniqid(),
            'name' => 'AII Regular', 'warehouse_type' => 'regular',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat '.uniqid(), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS '.uniqid(), 'code' => 'PCS'.uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'branch_id' => $hqBranchId, 'code' => 'PRD-'.uniqid(),
            'name' => 'Widget', 'category_id' => $catId, 'uom_id' => $uomId,
            'is_inventory_tracked' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $hqBranchId, 'product_id' => $productId,
            'sku' => 'SKU-'.uniqid(), 'variant_name' => 'Widget',
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $supplierId = DB::table('contacts')->insertGetId([
            'branch_id' => $hqBranchId, 'type' => 'supplier',
            'name' => 'Supplier '.uniqid(), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $poId = DB::table('purchase_orders')->insertGetId([
            'number' => 'PO-'.$id, 'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId, 'status' => 'received',
            'order_date' => '2026-09-01', 'currency_code' => 'IDR',
            'subtotal' => 0, 'tax_amount' => 0, 'total' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $grnId = DB::table('goods_receipts')->insertGetId([
            'number' => 'GRN-'.$id, 'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId, 'purchase_order_id' => $poId,
            'warehouse_id' => $hqWarehouseId, 'supplier_do_no' => 'DO-'.$id,
            'status' => 'approved', 'receipt_date' => '2026-09-02',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $invoiceId = DB::table('purchase_invoices')->insertGetId([
            'number' => 'FBL/HQ/20260923/001/A',
            'branch_id' => $hqBranchId, 'supplier_id' => $supplierId,
            'purchase_order_id' => $poId, 'goods_receipt_id' => $grnId,
            'status' => 'approved', 'invoice_date' => '2026-09-23',
            'currency_code' => 'IDR', 'is_tax_inclusive' => false,
            'subtotal' => 0, 'tax_amount' => 0, 'total' => 0,
            'returned_amount' => 0, 'paid_amount' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $originLayerId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $variantId,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 0, 'unit_cost' => 47500,
            'received_at' => now(),
            'source_type' => 'purchase_order', 'source_id' => $poId,
            'root_source_type' => 'purchase_order', 'root_source_id' => $poId,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'stlin_'.$id.'@acme.test', 'role' => 'user',
        ]);
        $companyUser = CompanyUser::create([
            'user_id' => $user->id, 'tenant_id' => $tenant->id,
            'role' => 'member', 'is_default' => true,
        ]);
        DB::table('company_user_branches')->insert([
            ['company_user_id' => $companyUser->id, 'branch_id' => $hqBranchId],
            ['company_user_id' => $companyUser->id, 'branch_id' => $branchId],
        ]);

        return [
            'tenantId' => $tenant->id,
            'hqBranchId' => (int) $hqBranchId,
            'hqWarehouseId' => (int) $hqWarehouseId,
            'branchWarehouseId' => (int) $branchWarehouseId,
            'variantId' => (int) $variantId,
            'poId' => (int) $poId,
            'grnId' => (int) $grnId,
            'invoiceId' => (int) $invoiceId,
            'originLayerId' => (int) $originLayerId,
            'user' => $user,
        ];
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            $safe = str_replace('"', '""', $schemaName);
            DB::statement('DROP SCHEMA IF EXISTS "'.$safe.'" CASCADE');
        } catch (\Throwable $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name FROM information_schema.schemata
                 WHERE schema_name NOT IN ('public', 'information_schema')
                 AND schema_name NOT LIKE 'pg_%'"
            );

            foreach ($schemas as $row) {
                if (str_starts_with($row->schema_name, 'sch_')) {
                    $this->dropSchema($row->schema_name);
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
