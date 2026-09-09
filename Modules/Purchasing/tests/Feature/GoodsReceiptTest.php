<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Warehouse\Application\Warehouse\CreateWarehousesForBranch;
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
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        // Ensure system warehouses exist (in case helper didn't create due to observer)
        app(CreateWarehousesForBranch::class)->ensureForAllBranches();
        $warehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        // Ensure HQ warehouse exists for PO header
        if (! $hqWarehouseId) {
            $hqWarehouseId = $warehouseId;
        }
        if (! $warehouseId) {
            $branchCode = DB::table('branches')->where('id', $branchBId)->value('code');
            $warehouseId = DB::table('warehouses')->insertGetId([
                'branch_id' => $branchBId,
                'code' => 'GD-'.$branchCode.'-REG',
                'name' => 'Gudang Regular Branch B',
                'warehouse_type' => 'regular',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $po = PurchaseOrder::create([
            'number' => 'PO-BRB-0001',
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Sent,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'branch_mode' => 'single',
        ]);
        $poItem = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'destination_warehouse_id' => $warehouseId,
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
            'status' => GoodsReceiptStatus::Draft,
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
        $this->assertSame(GoodsReceiptStatus::Posted->value, DB::table('goods_receipts')->where('id', $grnId)->value('status'));

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
        $this->assertSame(PurchaseOrderStatus::Received->value, $poDb->status);
        tenancy()->end();
    }

    public function test_store_goods_receipt_via_http_creates_draft_grn_with_items(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-002', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $warehouseId = $hqWarehouseId ?? $destWarehouseId;

        $po = PurchaseOrder::create([
            'number' => 'PO-STORE-'.uniqid(),
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Sent,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'branch_mode' => 'single',
        ]);
        $poItem = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'destination_warehouse_id' => $destWarehouseId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget Store',
            'sku' => 'SKU-STORE',
            'qty_ordered' => 15,
            'qty_received' => 0,
            'unit_price' => 25000,
        ]);

        $payload = [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $hqWarehouseId,
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
        $this->assertSame(GoodsReceiptStatus::Draft->value, $grn->status);
        $this->assertEquals($hqBranchId, $grn->branch_id);
        $this->assertEquals($supplierId, $grn->supplier_id);
        $this->assertEquals($hqWarehouseId, $grn->warehouse_id);

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

    public function test_show_goods_receipt_page_includes_po_number_and_warehouse(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-SHOW', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');

        $po = PurchaseOrder::create([
            'number' => 'PO-SHOW-'.uniqid(),
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Sent,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'branch_mode' => 'single',
        ]);
        $poItem = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget Show',
            'sku' => 'SKU-SHOW',
            'qty_ordered' => 15,
            'qty_received' => 0,
            'unit_price' => 25000,
        ]);

        $grn = GoodsReceipt::create([
            'number' => 'GRN-SHOW-'.uniqid(),
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $hqWarehouseId,
            'status' => GoodsReceiptStatus::Draft,
            'receipt_date' => now()->toDateString(),
        ]);
        $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget Show',
            'sku' => 'SKU-SHOW',
            'qty_received' => 10,
        ]);

        $grnId = $grn->id;
        $poNumber = $po->number;
        tenancy()->end();

        $response = $this->actingAs($user)->get(route('purchasing.grns.show', $grnId));
        $response->assertOk();

        $page = $response->inertiaPage();
        $this->assertSame($poNumber, $page['props']['goodsReceipt']['purchase_order']['number']);
        $this->assertSame($po->id, $page['props']['goodsReceipt']['purchase_order']['id']);
        $this->assertNotNull($page['props']['warehouse']);
        $this->assertSame((int) $hqWarehouseId, (int) $page['props']['warehouse']['id']);
    }

    public function test_post_partial_goods_receipt_updates_po_status_to_partially_received(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-PARTIAL', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        $po = PurchaseOrder::create([
            'number' => 'PO-PARTIAL-'.uniqid(),
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Sent,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'branch_mode' => 'single',
        ]);
        $poItem = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'destination_warehouse_id' => $destWarehouseId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget Partial',
            'sku' => 'SKU-PARTIAL',
            'qty_ordered' => 20,
            'qty_received' => 0,
            'unit_price' => 50000,
        ]);

        // GRN 1: partial (10 of 20)
        $grn1 = GoodsReceipt::create([
            'number' => 'GRN-P1-'.uniqid(),
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $hqWarehouseId,
            'status' => GoodsReceiptStatus::Draft,
            'receipt_date' => now()->toDateString(),
        ]);
        $grn1->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget Partial',
            'sku' => 'SKU-PARTIAL',
            'qty_received' => 10,
        ]);
        $grn1Id = $grn1->id;
        $poId = $po->id;
        tenancy()->end();

        $this->actingAs($user)->post(route('purchasing.grns.post', $grn1Id));

        tenancy()->initialize($tenantId);
        $poDb = DB::table('purchase_orders')->where('id', $poId)->first();
        $this->assertSame(PurchaseOrderStatus::PartiallyReceived->value, $poDb->status);
        $this->assertEquals(10, (float) DB::table('purchase_order_items')->where('id', $poItem->id)->value('qty_received'));

        // Attempt cancel while partially_received -> should fail
        tenancy()->end();
        $cancelResponse = $this->actingAs($user)->post(route('purchasing.orders.cancel', $poId));
        $cancelResponse->assertSessionHasErrors(['order']);

        // GRN 2: complete remaining 10
        tenancy()->initialize($tenantId);
        $grn2 = GoodsReceipt::create([
            'number' => 'GRN-P2-'.uniqid(),
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $poId,
            'warehouse_id' => $hqWarehouseId,
            'status' => GoodsReceiptStatus::Draft,
            'receipt_date' => now()->toDateString(),
        ]);
        $grn2->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget Partial',
            'sku' => 'SKU-PARTIAL',
            'qty_received' => 10,
        ]);
        $grn2Id = $grn2->id;
        tenancy()->end();

        $this->actingAs($user)->post(route('purchasing.grns.post', $grn2Id));

        tenancy()->initialize($tenantId);
        $poDbFinal = DB::table('purchase_orders')->where('id', $poId)->first();
        $this->assertSame(PurchaseOrderStatus::Received->value, $poDbFinal->status);
        $this->assertEquals(20, (float) DB::table('purchase_order_items')->where('id', $poItem->id)->value('qty_received'));
        tenancy()->end();
    }

    public function test_create_only_lists_receivable_purchase_orders(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        $supplierId = $this->createSupplier($branchBId);

        foreach (PurchaseOrderStatus::cases() as $status) {
            PurchaseOrder::create([
                'number' => 'PO-OPT-'.$status->value.'-'.uniqid(),
                'branch_id' => $hqBranchId,
                'supplier_id' => $supplierId,
                'status' => $status,
                'order_date' => now()->toDateString(),
                'currency_code' => 'IDR',
                'branch_mode' => 'single',
            ]);
        }
        tenancy()->end();

        $this->actingAs($user)->get(route('purchasing.grns.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('purchaseOrders', 3)
                ->where('purchaseOrders.0.status', PurchaseOrderStatus::PartiallyReceived->value)
                ->where('purchaseOrders.1.status', PurchaseOrderStatus::Sent->value)
                ->where('purchaseOrders.2.status', PurchaseOrderStatus::Approved->value)
                ->has('warehouses', 1)
                ->where('warehouses.0.code', 'GD-HQ-REG')
            );
    }

    public function test_store_rejects_non_hq_regular_warehouse(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $retailHqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'retail')->value('id');

        $po = PurchaseOrder::create([
            'number' => 'PO-WH-'.uniqid(),
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Sent,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'branch_mode' => 'single',
        ]);
        $poItem = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'destination_warehouse_id' => $retailHqWarehouseId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget',
            'sku' => 'SKU-WH',
            'qty_ordered' => 10,
            'qty_received' => 0,
            'unit_price' => 50000,
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.grns.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $retailHqWarehouseId,
            'receipt_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_variant_id' => $variantId,
                    'qty_received' => 10,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['warehouse_id']);
    }

    public function test_store_rejects_zero_qty_item(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        $po = PurchaseOrder::create([
            'number' => 'PO-ZERO-'.uniqid(),
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Sent,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'branch_mode' => 'single',
        ]);
        $poItem = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'destination_warehouse_id' => $destWarehouseId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget',
            'sku' => 'SKU-ZERO',
            'qty_ordered' => 10,
            'qty_received' => 0,
            'unit_price' => 50000,
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.grns.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $hqWarehouseId,
            'receipt_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_variant_id' => $variantId,
                    'qty_received' => 0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.qty_received']);
    }

    public function test_store_allows_same_variant_across_multiple_po_lines(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-MULTI', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        // Multi-branch PO repeating the same variant across 2 lines (allocated to different destinations)
        $po = PurchaseOrder::create([
            'number' => 'PO-MULTI-'.uniqid(),
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Sent,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'branch_mode' => 'multi',
        ]);
        $poItem1 = $po->items()->create([
            'destination_branch_id' => $hqBranchId,
            'destination_warehouse_id' => $hqWarehouseId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget Multi',
            'sku' => 'SKU-MULTI',
            'qty_ordered' => 10,
            'qty_received' => 0,
            'unit_price' => 50000,
        ]);
        $poItem2 = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'destination_warehouse_id' => $destWarehouseId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget Multi',
            'sku' => 'SKU-MULTI',
            'qty_ordered' => 20,
            'qty_received' => 0,
            'unit_price' => 50000,
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.grns.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $hqWarehouseId,
            'receipt_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem1->id,
                    'product_variant_id' => $variantId,
                    'qty_received' => 10,
                ],
                [
                    'purchase_order_item_id' => $poItem2->id,
                    'product_variant_id' => $variantId, // repeated intentionally
                    'qty_received' => 20,
                ],
            ],
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('purchasing.grns.index'));

        tenancy()->initialize($tenantId);
        $grn = DB::table('goods_receipts')->where('purchase_order_id', $po->id)->first();
        $this->assertNotNull($grn);
        $this->assertSame(2, (int) DB::table('goods_receipt_items')->where('goods_receipt_id', $grn->id)->count());
        tenancy()->end();
    }

    /**
     * @return array{0: string|int, 1: int, 2: int, 3: User}
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

        app(CreateWarehousesForBranch::class)->ensureForAllBranches();

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

        return [$tenant->id, (int) $hqBranchId, (int) $branchBId, $user];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createProductAndVariant(int $branchId, string $codePrefix = 'PRD', bool $variantActive = true): array
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
            'branch_id' => $branchId,
            'code' => $codePrefix.'-'.uniqid(),
            'name' => 'Widget '.uniqid(),
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
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
