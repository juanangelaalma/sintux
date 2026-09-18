<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Approval\Application\CreateApprovalRule;
use Modules\Approval\Models\ApprovalMapping;
use Modules\Approval\Models\ApprovalTransactionType;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Application\GoodsReceipt\ApproveGoodsReceipt;
use Modules\Purchasing\Enums\GoodsReceiptStatus;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Enums\PurchaseOrderStatus;
use Modules\Purchasing\Models\GoodsReceipt;
use Modules\Purchasing\Models\PurchaseOrder;
use Modules\Warehouse\Application\Warehouse\CreateWarehousesForBranch;
use Tests\TestCase;

class PurchaseInvoiceTest extends TestCase
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

    public function test_member_can_create_purchase_invoice(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $branchCode = DB::table('branches')->where('id', $hqBranchId)->value('code');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.invoices.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'invoice_date' => '2026-08-18',
            'due_date' => '2026-09-18',
            'note' => 'Tagihan Pembelian',
            'items' => [
                ['product_variant_id' => $variantId, 'qty' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $inv = DB::table('purchase_invoices')->where('branch_id', $hqBranchId)->first();
        $this->assertNotNull($inv);
        $this->assertSame(PurchaseInvoiceStatus::Approved->value, $inv->status);
        $this->assertSame('FBL/'.$branchCode.'/20260818/001/A', (string) $inv->number);
        $this->assertEquals(500000, (float) $inv->subtotal);
        tenancy()->end();
    }

    public function test_invoice_with_matching_rule_transitions_to_pending(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);

        $type = ApprovalTransactionType::where('key', 'purchase_invoice')->firstOrFail();
        app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $type->id,
            'name' => 'INV Rule',
            'min_amount' => 100000,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$user->id + 999]],
            ],
        ], $user->id);

        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.invoices.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'invoice_date' => '2026-08-18',
            'items' => [
                ['product_variant_id' => $variantId, 'qty' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $inv = DB::table('purchase_invoices')->where('branch_id', $hqBranchId)->first();
        $this->assertNotNull($inv);
        $this->assertSame(PurchaseInvoiceStatus::Pending->value, $inv->status);
        tenancy()->end();
    }

    public function test_3_way_match_validation_rejects_invoice_qty_exceeding_received_qty(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);

        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $po = PurchaseOrder::create([
            'number' => 'PO-BRB-0010',
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
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_ordered' => 20,
            'qty_received' => 5, // Only 5 received
            'unit_price' => 50000,
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.invoices.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'invoice_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItem->id,
                    'product_variant_id' => $variantId,
                    'qty' => 10, // Trying to invoice 10 > 5 received!
                    'unit_price' => 50000,
                ],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.qty']);
    }

    /**
     * Jatuh tempo dari PO yang sudah lampau tetap valid: faktur supplier
     * yang telat dicatat harus bisa disimpan (regresi prefill due_date).
     */
    public function test_invoice_with_past_due_date_from_po_is_accepted(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);

        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $po = PurchaseOrder::create([
            'number' => 'PO-DUE-001',
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Received,
            'order_date' => '2026-09-01',
            'due_date' => '2026-09-05',
            'currency_code' => 'IDR',
            'branch_mode' => 'single',
        ]);
        $poItem = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'destination_warehouse_id' => $destWarehouseId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_ordered' => 20,
            'qty_received' => 20,
            'unit_price' => 50000,
        ]);
        tenancy()->end();

        // Payload persis prefill FE: due_date ikut PO (lampau).
        $this->actingAs($user)->post(route('purchasing.invoices.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'invoice_date' => '2026-09-14',
            'due_date' => '2026-09-05',
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'product_variant_id' => $variantId,
                'qty' => 5,
                'unit_price' => 50000,
            ]],
        ])->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $inv = DB::table('purchase_invoices')->orderByDesc('id')->first();
        $this->assertNotNull($inv);
        $this->assertSame('2026-09-05', substr((string) $inv->due_date, 0, 10));
    }

    /**
     * Strict 1:1 — satu GRN tepat satu faktur HO penuh (qty = received,
     * harga = DO), PO otomatis closed saat tertagih penuh. Faktur kedua
     * untuk GRN yang sama dan qty/harga menyimpang ditolak.
     */
    public function test_cumulative_invoicing_capped_and_closes_po_when_full(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);

        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $po = PurchaseOrder::create([
            'number' => 'PO-CUM-001',
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
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_ordered' => 20,
            'qty_received' => 0,
            'unit_price' => 50000,
        ]);

        // GRN cabang (fisik) dengan harga DO 52000 (beda dari PO 50000).
        $grn = GoodsReceipt::create([
            'number' => 'GRN-CUM-001',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $hqWarehouseId,
            'supplier_do_no' => 'DO-CUM-001',
            'status' => GoodsReceiptStatus::Submitted,
            'receipt_date' => now()->toDateString(),
        ]);
        $grnItem = $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_received' => 20,
            'unit_price_supplier' => 52000,
        ]);

        app(ApproveGoodsReceipt::class)->execute($grn->id, $user->id);
        tenancy()->end();

        // Stok memakai harga DO, bukan PO.
        tenancy()->initialize($tenantId);
        $this->assertEquals(52000, (float) DB::table('stock_layers')->orderByDesc('id')->value('unit_cost'));
        tenancy()->end();

        $payload = fn (float $qty, float $price) => [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'goods_receipt_id' => $grn->id,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'goods_receipt_item_id' => $grnItem->id,
                'product_variant_id' => $variantId,
                'qty' => $qty,
                'unit_price' => $price,
            ]],
        ];

        // Qty kurang dari received ditolak (harus penuh 1:1).
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $payload(19, 52000))
            ->assertSessionHasErrors(['items.0.qty']);

        // Harga PO ditolak (harus harga DO).
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $payload(20, 50000))
            ->assertSessionHasErrors(['items.0.unit_price']);

        // Faktur penuh 20 @ DO → approved, counter 20, PO closed.
        $this->actingAs($user)->post(route('purchasing.invoices.store'), $payload(20, 52000))
            ->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $this->assertEquals(20, (float) DB::table('goods_receipt_items')->where('id', $grnItem->id)->value('qty_invoiced'));
        $this->assertEquals(20, (float) DB::table('purchase_order_items')->where('id', $poItem->id)->value('qty_invoiced'));
        $this->assertSame(PurchaseOrderStatus::Closed->value, DB::table('purchase_orders')->where('id', $po->id)->value('status'));
        // Total memakai harga DO: 20 * 52000.
        $this->assertEquals(1040000, (float) DB::table('purchase_invoices')->orderByDesc('id')->value('total'));
        tenancy()->end();

        // Faktur kedua untuk GRN yang sama ditolak.
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $payload(20, 52000))
            ->assertSessionHasErrors(['goods_receipt_id']);
    }

    /**
     * Counter tidak bergerak saat faktur masih Pending; naik + PO closed
     * setelah approval.
     */
    public function test_counter_moves_only_on_approval(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $approver = User::factory()->create(['email' => 'approver_'.$tenantId.'@acme.test', 'role' => 'user']);
        CompanyUser::create([
            'user_id' => $approver->id,
            'tenant_id' => $tenantId,
            'role' => 'member',
            'branch_id' => $hqBranchId,
            'is_default' => false,
        ]);

        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);

        $type = ApprovalTransactionType::where('key', 'purchase_invoice')->firstOrFail();
        app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $type->id,
            'name' => 'INV Rule',
            'min_amount' => 100000,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$approver->id]],
            ],
        ], $user->id);

        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $po = PurchaseOrder::create([
            'number' => 'PO-APPR-001',
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Received,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'branch_mode' => 'single',
        ]);
        $poItem = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'destination_warehouse_id' => $destWarehouseId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_ordered' => 10,
            'qty_received' => 10,
            'unit_price' => 50000,
        ]);
        tenancy()->end();

        $this->actingAs($user)->post(route('purchasing.invoices.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'product_variant_id' => $variantId,
                'qty' => 10,
                'unit_price' => 50000,
            ]],
        ])->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $invId = DB::table('purchase_invoices')->orderByDesc('id')->value('id');
        $mappingId = ApprovalMapping::where('transaction_type', 'purchase_invoice')
            ->where('transaction_id', $invId)
            ->value('id');
        $this->assertNotNull($mappingId);
        // Masih pending: counter tetap 0.
        $this->assertEquals(0, (float) DB::table('purchase_order_items')->where('id', $poItem->id)->value('qty_invoiced'));
        tenancy()->end();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        $this->actingAs($approver)->post(route('approval.mappings.approve', $mappingId), [])
            ->assertSessionHasNoErrors();

        tenancy()->initialize($tenantId);
        $this->assertEquals(10, (float) DB::table('purchase_order_items')->where('id', $poItem->id)->value('qty_invoiced'));
        $this->assertSame(PurchaseInvoiceStatus::Approved->value, DB::table('purchase_invoices')->where('id', $invId)->value('status'));
        $this->assertSame(PurchaseOrderStatus::Closed->value, DB::table('purchase_orders')->where('id', $po->id)->value('status'));
    }

    /**
     * Faktur HO dari GRN cabang posted: qty = received, harga = DO.
     * Tolak GRN draft, harga PO, qty menyimpang, dan faktur kedua.
     */
    public function test_invoice_from_posted_grn_and_rejections(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        $po = PurchaseOrder::create([
            'number' => 'PO-GRN-001',
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
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_ordered' => 20,
            'qty_received' => 0,
            'unit_price' => 50000,
        ]);

        $grn = GoodsReceipt::create([
            'number' => 'GRN-INV-001',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $hqWarehouseId,
            'supplier_do_no' => 'DO-INV-001',
            'status' => GoodsReceiptStatus::Submitted,
            'receipt_date' => now()->toDateString(),
        ]);
        $grnItem = $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_received' => 12,
            'unit_price_supplier' => 52000,
        ]);

        $draftGrn = GoodsReceipt::create([
            'number' => 'GRN-INV-002',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $hqWarehouseId,
            'supplier_do_no' => 'DO-INV-002',
            'status' => GoodsReceiptStatus::Draft,
            'receipt_date' => now()->toDateString(),
        ]);
        $draftGrnItem = $draftGrn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_received' => 5,
            'unit_price_supplier' => 52000,
        ]);
        tenancy()->end();

        // GRN draft tidak bisa difaktur (hutang HO atas GRN cabang posted).
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), [
                'branch_id' => $hqBranchId,
                'supplier_id' => $supplierId,
                'purchase_order_id' => $po->id,
                'goods_receipt_id' => $draftGrn->id,
                'invoice_date' => now()->toDateString(),
                'items' => [[
                    'purchase_order_item_id' => $poItem->id,
                    'goods_receipt_item_id' => $draftGrnItem->id,
                    'product_variant_id' => $variantId,
                    'qty' => 5,
                    'unit_price' => 52000,
                ]],
            ])
            ->assertSessionHasErrors(['goods_receipt_id']);

        // Approve GRN pertama (12 diterima → posting + transfer).
        tenancy()->initialize($tenantId);
        app(ApproveGoodsReceipt::class)->execute($grn->id, $user->id);
        tenancy()->end();

        $grnPayload = fn (float $qty, float $price) => [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'goods_receipt_id' => $grn->id,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'goods_receipt_item_id' => $grnItem->id,
                'product_variant_id' => $variantId,
                'qty' => $qty,
                'unit_price' => $price,
            ]],
        ];

        // Harga PO ditolak (harus harga DO 52000).
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $grnPayload(12, 50000))
            ->assertSessionHasErrors(['items.0.unit_price']);

        // Qty kurang dari received ditolak.
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $grnPayload(11, 52000))
            ->assertSessionHasErrors(['items.0.qty']);

        // Tagih 12 @ DO → approved + counter naik + total ikut DO.
        $this->actingAs($user)->post(route('purchasing.invoices.store'), $grnPayload(12, 52000))
            ->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $this->assertEquals(12, (float) DB::table('goods_receipt_items')->where('id', $grnItem->id)->value('qty_invoiced'));
        $this->assertEquals(12, (float) DB::table('purchase_order_items')->where('id', $poItem->id)->value('qty_invoiced'));

        $inv = DB::table('purchase_invoices')->orderByDesc('id')->first();
        $this->assertSame($grn->id, (int) $inv->goods_receipt_id);
        $this->assertSame($hqBranchId, (int) $inv->branch_id);
        $this->assertEquals(624000, (float) $inv->total);
        $this->assertStringStartsWith('FBL/', (string) $inv->number);
        tenancy()->end();

        // Faktur kedua untuk GRN yang sama ditolak.
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $grnPayload(12, 52000))
            ->assertSessionHasErrors(['goods_receipt_id']);
    }

    /**
     * Satu varian boleh muncul di banyak baris selama menunjuk baris GRN
     * berbeda (mis. GRN dari PO alokasi multi-cabang). Regresi: aturan
     * distinct lama menolak payload prefill yang sah.
     */
    public function test_duplicate_variant_across_grn_lines_is_accepted(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $warehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        $po = PurchaseOrder::create([
            'number' => 'PO-DUP-001',
            'branch_id' => $hqBranchId,
            'warehouse_id' => $hqWarehouseId,
            'supplier_id' => $supplierId,
            'status' => PurchaseOrderStatus::Sent,
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'branch_mode' => 'single',
        ]);
        $poItemA = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'destination_warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_ordered' => 300,
            'qty_received' => 0,
            'unit_price' => 50000,
        ]);
        $poItemB = $po->items()->create([
            'destination_branch_id' => $branchBId,
            'destination_warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_ordered' => 200,
            'qty_received' => 0,
            'unit_price' => 50000,
        ]);

        $grn = GoodsReceipt::create([
            'number' => 'GRN-DUP-001',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $hqWarehouseId,
            'supplier_do_no' => 'DO-DUP-001',
            'status' => GoodsReceiptStatus::Submitted,
            'receipt_date' => now()->toDateString(),
        ]);
        $grnItemA = $grn->items()->create([
            'purchase_order_item_id' => $poItemA->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_received' => 300,
            'unit_price_supplier' => 52000,
        ]);
        $grnItemB = $grn->items()->create([
            'purchase_order_item_id' => $poItemB->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_received' => 200,
            'unit_price_supplier' => 51000,
        ]);
        app(ApproveGoodsReceipt::class)->execute($grn->id, $user->id);
        tenancy()->end();

        $this->actingAs($user)->post(route('purchasing.invoices.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'goods_receipt_id' => $grn->id,
            'invoice_date' => now()->toDateString(),
            'items' => [
                [
                    'purchase_order_item_id' => $poItemA->id,
                    'goods_receipt_item_id' => $grnItemA->id,
                    'product_variant_id' => $variantId,
                    'qty' => 300,
                    'unit_price' => 52000,
                ],
                [
                    'purchase_order_item_id' => $poItemB->id,
                    'goods_receipt_item_id' => $grnItemB->id,
                    'product_variant_id' => $variantId,
                    'qty' => 200,
                    'unit_price' => 51000,
                ],
            ],
        ])->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $inv = DB::table('purchase_invoices')->orderByDesc('id')->first();
        $this->assertNotNull($inv);
        $this->assertSame($grn->id, (int) $inv->goods_receipt_id);
        $this->assertSame(2, DB::table('purchase_invoice_items')->where('purchase_invoice_id', $inv->id)->count());
        $this->assertEquals(300, (float) DB::table('goods_receipt_items')->where('id', $grnItemA->id)->value('qty_invoiced'));
        $this->assertEquals(200, (float) DB::table('goods_receipt_items')->where('id', $grnItemB->id)->value('qty_invoiced'));
    }

    /**
     * @return array{0: string|int, 1: int, 2: int, 3: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('inv_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Invoice Test-'.$id,
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

        // Create 3 system warehouses per branch
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

        return [$tenant->id, (int) $branchBId, (int) $hqBranchId, $user];
    }

    public function test_non_hq_branch_is_forbidden_from_purchase_invoices(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        $this->actingAs($user)->get(route('purchasing.invoices.index'))->assertForbidden();
        $this->actingAs($user)->post(route('purchasing.invoices.store'), [])->assertForbidden();
    }

    public function test_hq_branch_can_view_purchase_invoices_index(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $this->actingAs($user)->get(route('purchasing.invoices.index'))->assertOk();
    }

    /**
     * Pembebanan hutang selalu di HO: faktur dengan branch cabang ditolak
     * walau user HO yang membuatkan.
     */
    public function test_invoice_for_non_hq_branch_is_rejected(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        tenancy()->end();

        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), [
                'branch_id' => $branchBId,
                'supplier_id' => $supplierId,
                'invoice_date' => '2026-08-18',
                'items' => [
                    ['product_variant_id' => $variantId, 'qty' => 10, 'unit_price' => 50000],
                ],
            ])
            ->assertSessionHasErrors(['branch_id']);
    }

    /**
     * Referensi dokumen penagih: no. faktur supplier auto-isi dari DO dan
     * wajib sama; no. faktur pajak unik per supplier (bukan global).
     */
    public function test_supplier_and_tax_invoice_nos(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $supplier2Id = $this->createSupplier($branchBId);

        $hqWarehouseId = DB::table('warehouses')->where('branch_id', $hqBranchId)->where('warehouse_type', 'regular')->value('id');
        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        $makePoGrn = function (string $poNumber, int $supplier, float $qty, float $doPrice, ?string $doInvNo) use ($hqBranchId, $hqWarehouseId, $branchBId, $destWarehouseId, $variantId): array {
            $po = PurchaseOrder::create([
                'number' => $poNumber,
                'branch_id' => $hqBranchId,
                'warehouse_id' => $hqWarehouseId,
                'supplier_id' => $supplier,
                'status' => PurchaseOrderStatus::Sent,
                'order_date' => now()->toDateString(),
                'currency_code' => 'IDR',
                'branch_mode' => 'single',
            ]);
            $poItem = $po->items()->create([
                'destination_branch_id' => $branchBId,
                'destination_warehouse_id' => $destWarehouseId,
                'product_variant_id' => $variantId,
                'product_name' => 'Widget PRD',
                'sku' => 'SKU-PRD',
                'qty_ordered' => $qty,
                'qty_received' => 0,
                'unit_price' => 50000,
            ]);
            $grn = GoodsReceipt::create([
                'number' => 'GRN-'.$poNumber,
                'branch_id' => $branchBId,
                'supplier_id' => $supplier,
                'purchase_order_id' => $po->id,
                'warehouse_id' => $hqWarehouseId,
                'supplier_do_no' => 'DO-'.$poNumber,
                'supplier_invoice_no' => $doInvNo,
                'status' => GoodsReceiptStatus::Approved,
                'receipt_date' => now()->toDateString(),
            ]);
            $grnItem = $grn->items()->create([
                'purchase_order_item_id' => $poItem->id,
                'product_variant_id' => $variantId,
                'product_name' => 'Widget PRD',
                'sku' => 'SKU-PRD',
                'qty_received' => $qty,
                'unit_price_supplier' => $doPrice,
            ]);
            DB::table('purchase_order_items')->where('id', $poItem->id)->update(['qty_received' => $qty]);

            return [$po, $poItem, $grn, $grnItem];
        };

        [$po1, $poItem1, $grn1, $grnItem1] = $makePoGrn('PO-TAX-001', $supplierId, 10, 52000, 'SI-001');
        [$po2, $poItem2, $grn2, $grnItem2] = $makePoGrn('PO-TAX-002', $supplierId, 5, 52000, 'SI-002');
        [$po3, $poItem3, $grn3, $grnItem3] = $makePoGrn('PO-TAX-003', $supplier2Id, 5, 52000, null);
        tenancy()->end();

        $payload = fn (int $poId, int $poItem, int $grnId, int $grnItem, ?string $supNo, ?string $taxNo) => array_filter([
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $poId,
            'goods_receipt_id' => $grnId,
            'supplier_invoice_no' => $supNo,
            'tax_invoice_no' => $taxNo,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $poItem,
                'goods_receipt_item_id' => $grnItem,
                'product_variant_id' => $variantId,
                'qty' => $grnItem === $grnItem1->id ? 10 : 5,
                'unit_price' => 52000,
            ]],
        ], fn ($v) => $v !== null);

        // No. faktur supplier beda dari DO ditolak.
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $payload($po1->id, $poItem1->id, $grn1->id, $grnItem1->id, 'SI-X', null))
            ->assertSessionHasErrors(['supplier_invoice_no']);

        // Tanpa input no. supplier → auto-isi dari DO + simpan no. pajak.
        $this->actingAs($user)->post(
            route('purchasing.invoices.store'),
            $payload($po1->id, $poItem1->id, $grn1->id, $grnItem1->id, null, 'TAX-001')
        )->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $inv1 = DB::table('purchase_invoices')->where('goods_receipt_id', $grn1->id)->first();
        $this->assertSame('SI-001', (string) $inv1->supplier_invoice_no);
        $this->assertSame('TAX-001', (string) $inv1->tax_invoice_no);
        tenancy()->end();

        // No. faktur supplier ganda untuk supplier yang sama ditolak.
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $payload($po2->id, $poItem2->id, $grn2->id, $grnItem2->id, 'SI-001', null))
            ->assertSessionHasErrors(['supplier_invoice_no']);

        // No. faktur pajak ganda untuk supplier yang sama ditolak.
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $payload($po2->id, $poItem2->id, $grn2->id, $grnItem2->id, 'SI-002', 'TAX-001'))
            ->assertSessionHasErrors(['tax_invoice_no']);

        // Pasangan nomor yang benar untuk GRN kedua lolos.
        $this->actingAs($user)->post(
            route('purchasing.invoices.store'),
            $payload($po2->id, $poItem2->id, $grn2->id, $grnItem2->id, 'SI-002', 'TAX-002')
        )->assertRedirect(route('purchasing.invoices.index'));

        // Nomor yang sama untuk supplier BERBEDA tetap lolos.
        $payload3 = $payload($po3->id, $poItem3->id, $grn3->id, $grnItem3->id, 'SI-001', 'TAX-001');
        $payload3['supplier_id'] = $supplier2Id;
        $this->actingAs($user)->post(route('purchasing.invoices.store'), $payload3)
            ->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $inv3 = DB::table('purchase_invoices')->where('goods_receipt_id', $grn3->id)->first();
        $this->assertSame('SI-001', (string) $inv3->supplier_invoice_no);
        $this->assertSame('TAX-001', (string) $inv3->tax_invoice_no);
        tenancy()->end();
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
        $this->deleteIfTableExists('purchase_invoice_items');
        $this->deleteIfTableExists('purchase_invoices');
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
