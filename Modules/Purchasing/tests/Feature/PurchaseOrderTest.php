<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Approval\Application\CreateApprovalRule;
use Modules\Approval\Models\ApprovalTransactionType;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Warehouse\Application\Warehouse\CreateWarehousesForBranch;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
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

    public function test_member_can_create_purchase_order(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $branchCode = DB::table('branches')->where('id', $hqBranchId)->value('code');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-18',
            'expected_date' => '2026-08-25',
            'note' => 'Pesanan Pembelian Resmi',
            'items' => [
                ['destination_branch_id' => $branchBId, 'destination_warehouse_id' => $destWarehouseId, 'destination_expected_date' => '2026-08-28', 'product_variant_id' => $variantId, 'qty_ordered' => 20, 'unit_price' => 100000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.orders.index'));

        tenancy()->initialize($tenantId);
        $po = DB::table('purchase_orders')->where('branch_id', $hqBranchId)->first();
        $this->assertNotNull($po);
        $this->assertSame(PurchaseOrderStatus::Approved->value, $po->status);
        $expectedPrefix = 'PO/'.$branchCode.'/'.date('Y/m/d').'/000';
        $this->assertStringStartsWith('PO/'.$branchCode.'/', (string) $po->number);
        $this->assertEquals(2000000, (float) $po->subtotal);

        $items = DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->get();
        $this->assertCount(1, $items);
        $this->assertEquals($branchBId, (int) $items->first()->destination_branch_id);
        $this->assertEquals($destWarehouseId, (int) $items->first()->destination_warehouse_id);
        $this->assertEquals('2026-08-28', substr((string) $items->first()->destination_expected_date, 0, 10));
        $this->assertEquals(20, (float) $items->first()->qty_ordered);
        $this->assertEquals(0, (float) $items->first()->qty_received);
        $this->assertEquals(100000, (float) $items->first()->unit_price);
        tenancy()->end();
    }

    public function test_po_with_matching_rule_transitions_to_pending(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        $type = ApprovalTransactionType::where('key', 'purchase_order')->firstOrFail();
        app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $type->id,
            'name' => 'PO Rule',
            'min_amount' => 100000,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$user->id + 999]], // different user as approver
            ],
        ], $user->id);

        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-18',
            'expected_date' => '2026-08-25',
            'items' => [
                ['destination_branch_id' => $branchBId, 'destination_warehouse_id' => $destWarehouseId, 'destination_expected_date' => '2026-08-28', 'product_variant_id' => $variantId, 'qty_ordered' => 20, 'unit_price' => 100000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.orders.index'));

        tenancy()->initialize($tenantId);
        $po = DB::table('purchase_orders')->where('branch_id', $hqBranchId)->first();
        $this->assertNotNull($po);
        $this->assertSame(PurchaseOrderStatus::Pending->value, $po->status);
        tenancy()->end();
    }

    public function test_send_po_transitions_approved_to_sent(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        $supplierId = $this->createSupplier($branchBId);

        $po = PurchaseOrder::create([
            'number' => 'PO-HQ-0002',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Approved,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $poId = $po->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.send', $poId));
        $response->assertRedirect(route('purchasing.orders.show', $poId));

        tenancy()->initialize($tenantId);
        $this->assertSame(PurchaseOrderStatus::Sent->value, DB::table('purchase_orders')->where('id', $poId)->value('status'));
        tenancy()->end();
    }

    public function test_cancel_po_transitions_to_cancelled(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        $supplierId = $this->createSupplier($branchBId);

        $po = PurchaseOrder::create([
            'number' => 'PO-HQ-0003',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Pending,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $poId = $po->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.cancel', $poId));
        $response->assertRedirect(route('purchasing.orders.show', $poId));

        tenancy()->initialize($tenantId);
        $this->assertSame(PurchaseOrderStatus::Cancelled->value, DB::table('purchase_orders')->where('id', $poId)->value('status'));
        tenancy()->end();
    }

    public function test_store_persists_supplier_snapshot_fields_item_description_and_tags(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        $this->assertSame(5, (int) DB::table('purchase_tags')->count(), 'Migration should seed default tags');
        $tagUrgentId = (int) DB::table('purchase_tags')->where('name', 'Urgent')->value('id');
        $tagImporId = (int) DB::table('purchase_tags')->where('name', 'Impor')->value('id');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'supplier_email' => 'sales@supplier.test',
            'supplier_reference' => 'SUP-REF-8899',
            'billing_address' => 'Jl. Indonesia Blok C No. 22',
            'order_date' => '2026-08-26',
            'items' => [
                [
                    'destination_branch_id' => $branchBId,
                    'destination_warehouse_id' => $destWarehouseId,
                    'destination_expected_date' => '2026-08-30',
                    'product_variant_id' => $variantId,
                    'description' => 'Wireless mouse hitam',
                    'qty_ordered' => 5,
                    'unit_price' => 250000,
                ],
            ],
            'tag_ids' => [$tagUrgentId, $tagImporId],
        ]);

        $response->assertRedirect(route('purchasing.orders.index'));

        tenancy()->initialize($tenantId);
        $po = DB::table('purchase_orders')->where('branch_id', $hqBranchId)->first();
        $this->assertNotNull($po);
        $this->assertSame('sales@supplier.test', $po->supplier_email);
        $this->assertSame('SUP-REF-8899', $po->supplier_reference);
        $this->assertSame('Jl. Indonesia Blok C No. 22', $po->billing_address);

        $item = DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->first();
        $this->assertSame('Wireless mouse hitam', $item->description);
        $this->assertEquals($branchBId, (int) $item->destination_branch_id);

        $linkedTags = DB::table('purchase_order_purchase_tag')
            ->where('purchase_order_id', $po->id)
            ->pluck('purchase_tag_id')
            ->sort()
            ->values();
        $this->assertSame(
            collect([$tagUrgentId, $tagImporId])->sort()->values()->all(),
            $linkedTags->all(),
        );
        tenancy()->end();
    }

    public function test_store_persists_multi_branch_allocations(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantAId] = $this->createProductAndVariant($branchBId, 'PRD-A', true);
        // Create second branch C for multi-branch
        $branchCCode = 'BRC_'.uniqid();
        $branchCId = DB::table('branches')->insertGetId([
            'name' => 'Branch C',
            'code' => $branchCCode,
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $warehouseService = app(CreateWarehousesForBranch::class);
        $warehouseService->execute((int) $branchCId, $branchCCode, 'Branch C');
        [$variantBId] = $this->createProductAndVariant((int) $branchCId, 'PRD-B', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseB = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseC = DB::table('warehouses')->where('branch_id', $branchCId)->where('warehouse_type', 'regular')->value('id');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-26',
            'items' => [
                [
                    'destination_branch_id' => $branchBId,
                    'destination_warehouse_id' => $destWarehouseB,
                    'destination_expected_date' => '2026-08-30',
                    'product_variant_id' => $variantAId,
                    'qty_ordered' => 10,
                    'unit_price' => 50000,
                ],
                [
                    'destination_branch_id' => $branchCId,
                    'destination_warehouse_id' => $destWarehouseC,
                    'destination_expected_date' => '2026-08-27',
                    'product_variant_id' => $variantBId,
                    'qty_ordered' => 5,
                    'unit_price' => 20000,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchasing.orders.index'));

        tenancy()->initialize($tenantId);
        $po = DB::table('purchase_orders')->where('branch_id', $hqBranchId)->first();
        $this->assertNotNull($po);
        $this->assertSame('multi', $po->branch_mode);

        $items = DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->orderBy('destination_expected_date')->get();
        $this->assertCount(2, $items);
        // Sorted ASC by destination_expected_date: C (27) first, B (30) second
        $this->assertEquals($branchCId, (int) $items[0]->destination_branch_id);
        $this->assertEquals($branchBId, (int) $items[1]->destination_branch_id);
        $this->assertEquals('2026-08-27', substr((string) $items[0]->destination_expected_date, 0, 10));
        $this->assertEquals('2026-08-30', substr((string) $items[1]->destination_expected_date, 0, 10));
        tenancy()->end();
    }

    public function test_store_defaults_branch_mode_to_single_when_one_destination(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-26',
            'items' => [
                ['destination_branch_id' => $branchBId, 'destination_warehouse_id' => $destWarehouseId, 'destination_expected_date' => '2026-08-28', 'product_variant_id' => $variantId, 'qty_ordered' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.orders.index'));

        tenancy()->initialize($tenantId);
        $po = DB::table('purchase_orders')->where('branch_id', $hqBranchId)->first();
        $this->assertNotNull($po);
        $this->assertSame('single', $po->branch_mode);
        tenancy()->end();
    }

    public function test_non_headquarters_branch_is_forbidden_from_creating_purchase_order(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $branchBId,
            'warehouse_id' => $destWarehouseId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-26',
            'items' => [
                ['destination_branch_id' => $branchBId, 'destination_warehouse_id' => $destWarehouseId, 'destination_expected_date' => '2026-08-28', 'product_variant_id' => $variantId, 'qty_ordered' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertForbidden();
    }

    public function test_validation_rejects_non_hq_branch_in_payload_for_purchase_order(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $branchBId, // Non-HQ branch rejected by FormRequest validation!
            'warehouse_id' => $destWarehouseId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-26',
            'items' => [
                ['destination_branch_id' => $branchBId, 'destination_warehouse_id' => $destWarehouseId, 'destination_expected_date' => '2026-08-28', 'product_variant_id' => $variantId, 'qty_ordered' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertSessionHasErrors(['branch_id']);
    }

    public function test_validation_rejects_non_regular_warehouse_for_purchase_order(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $retailHqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'retail')->value('id');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $hqBranchId,
            'warehouse_id' => $retailHqWarehouseId, // Retail warehouse rejected for PO header!
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-26',
            'items' => [
                ['destination_branch_id' => $branchBId, 'destination_warehouse_id' => $destWarehouseId, 'destination_expected_date' => '2026-08-28', 'product_variant_id' => $variantId, 'qty_ordered' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertSessionHasErrors(['warehouse_id']);
    }

    public function test_validation_rejects_destination_warehouse_mismatch_with_destination_branch(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-26',
            'items' => [
                [
                    'destination_branch_id' => $branchBId,
                    'destination_warehouse_id' => $hqWarehouseId, // Warehouse HQ does not belong to branch B!
                    'destination_expected_date' => '2026-08-28',
                    'product_variant_id' => $variantId,
                    'qty_ordered' => 10,
                    'unit_price' => 50000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.destination_warehouse_id']);
    }

    public function test_validation_rejects_variant_belonging_to_different_branch_than_destination(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        // Variant created for HQ branch, not branch B
        [$variantHqId] = $this->createProductAndVariant($hqBranchId, 'PRD-HQ', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-26',
            'items' => [
                [
                    'destination_branch_id' => $branchBId,
                    'destination_warehouse_id' => $destWarehouseId,
                    'destination_expected_date' => '2026-08-28',
                    'product_variant_id' => $variantHqId, // Variant belongs to HQ, but destination is branch B!
                    'qty_ordered' => 10,
                    'unit_price' => 50000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.product_variant_id']);
    }

    public function test_validation_rejects_inconsistent_expected_date_for_same_branch(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantAId] = $this->createProductAndVariant($branchBId, 'PRD-A', true);
        [$variantBId] = $this->createProductAndVariant($branchBId, 'PRD-B', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-26',
            'items' => [
                ['destination_branch_id' => $branchBId, 'destination_warehouse_id' => $destWarehouseId, 'destination_expected_date' => '2026-08-27', 'product_variant_id' => $variantAId, 'qty_ordered' => 5, 'unit_price' => 10000],
                ['destination_branch_id' => $branchBId, 'destination_warehouse_id' => $destWarehouseId, 'destination_expected_date' => '2026-08-30', 'product_variant_id' => $variantBId, 'qty_ordered' => 3, 'unit_price' => 15000],
            ],
        ]);

        $response->assertSessionHasErrors(['items.1.destination_expected_date']);
    }

    public function test_validation_rejects_expected_date_before_order_date(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-26',
            'items' => [
                ['destination_branch_id' => $branchBId, 'destination_warehouse_id' => $destWarehouseId, 'destination_expected_date' => '2026-08-20', 'product_variant_id' => $variantId, 'qty_ordered' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.destination_expected_date']);
    }

    /**
     * @return array{0: string|int, 1: int, 2: int, 3: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('po_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'PO Test-'.$id,
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

        $branchBCode = 'BRB_'.uniqid();
        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => $branchBCode,
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create 3 system warehouses per branch (REG/RIT/KON)
        $warehouseService = app(CreateWarehousesForBranch::class);
        $warehouseService->execute((int) $hqBranchId, 'HQ', 'HQ Branch');
        $warehouseService->execute((int) $branchBId, $branchBCode, 'Branch B');

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
