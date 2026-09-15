<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Approval\Application\CreateApprovalRule;
use Modules\Approval\Models\ApprovalMapping;
use Modules\Approval\Models\ApprovalTransactionType;
use Modules\Company\Models\CompanyUser;
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
        $branchCode = DB::table('branches')->where('id', $branchBId)->value('code');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.invoices.store'), [
            'branch_id' => $branchBId,
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
        $inv = DB::table('purchase_invoices')->where('branch_id', $branchBId)->first();
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
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'invoice_date' => '2026-08-18',
            'items' => [
                ['product_variant_id' => $variantId, 'qty' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $inv = DB::table('purchase_invoices')->where('branch_id', $branchBId)->first();
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
        $po = PurchaseOrder::create([
            'number' => 'PO-BRB-0010',
            'branch_id' => $branchBId,
            'warehouse_id' => $destWarehouseId,
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
            'branch_id' => $branchBId,
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
        $po = PurchaseOrder::create([
            'number' => 'PO-DUE-001',
            'branch_id' => $branchBId,
            'warehouse_id' => $destWarehouseId,
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
            'branch_id' => $branchBId,
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
     * Faktur kedua atas PO item yang sama lolos bila dalam sisa,
     * dan PO otomatis closed saat tertagih penuh.
     */
    public function test_cumulative_invoicing_capped_and_closes_po_when_full(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);

        $destWarehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');
        $po = PurchaseOrder::create([
            'number' => 'PO-CUM-001',
            'branch_id' => $branchBId,
            'warehouse_id' => $destWarehouseId,
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
            'qty_ordered' => 20,
            'qty_received' => 20,
            'unit_price' => 50000,
        ]);
        tenancy()->end();

        $payload = fn (float $qty) => [
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $poItem->id,
                'product_variant_id' => $variantId,
                'qty' => $qty,
                'unit_price' => 50000,
            ]],
        ];

        // Faktur 1: 12 dari 20 → approved, counter 12, PO belum closed.
        $this->actingAs($user)->post(route('purchasing.invoices.store'), $payload(12))
            ->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $this->assertEquals(12, (float) DB::table('purchase_order_items')->where('id', $poItem->id)->value('qty_invoiced'));
        $this->assertSame(PurchaseOrderStatus::Received->value, DB::table('purchase_orders')->where('id', $po->id)->value('status'));
        tenancy()->end();

        // Faktur 2: 8 sisa → approved, counter 20, PO closed.
        $this->actingAs($user)->post(route('purchasing.invoices.store'), $payload(8))
            ->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $this->assertEquals(20, (float) DB::table('purchase_order_items')->where('id', $poItem->id)->value('qty_invoiced'));
        $this->assertSame(PurchaseOrderStatus::Closed->value, DB::table('purchase_orders')->where('id', $po->id)->value('status'));
        tenancy()->end();

        // Faktur 3: 1 melebihi sisa → ditolak.
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $payload(1))
            ->assertSessionHasErrors(['items.0.qty']);
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
        $po = PurchaseOrder::create([
            'number' => 'PO-APPR-001',
            'branch_id' => $branchBId,
            'warehouse_id' => $destWarehouseId,
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
            'branch_id' => $branchBId,
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
     * Faktur dari GRN: happy path per sisa GRN, tolak harga beda PO,
     * tolak GRN draft.
     */
    public function test_invoice_from_posted_grn_and_rejections(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant($branchBId, 'PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $warehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        $po = PurchaseOrder::create([
            'number' => 'PO-GRN-001',
            'branch_id' => $branchBId,
            'warehouse_id' => $warehouseId,
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
            'number' => 'GRN-INV-001',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouseId,
            'status' => GoodsReceiptStatus::Draft,
            'receipt_date' => now()->toDateString(),
        ]);
        $grnItem = $grn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_received' => 12,
        ]);

        $draftGrn = GoodsReceipt::create([
            'number' => 'GRN-INV-002',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'warehouse_id' => $warehouseId,
            'status' => GoodsReceiptStatus::Draft,
            'receipt_date' => now()->toDateString(),
        ]);
        $draftGrnItem = $draftGrn->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_received' => 5,
        ]);
        tenancy()->end();

        // GRN draft tidak bisa difaktur.
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), [
                'branch_id' => $branchBId,
                'supplier_id' => $supplierId,
                'purchase_order_id' => $po->id,
                'goods_receipt_id' => $draftGrn->id,
                'invoice_date' => now()->toDateString(),
                'items' => [[
                    'purchase_order_item_id' => $poItem->id,
                    'goods_receipt_item_id' => $draftGrnItem->id,
                    'product_variant_id' => $variantId,
                    'qty' => 5,
                    'unit_price' => 50000,
                ]],
            ])
            ->assertSessionHasErrors(['goods_receipt_id']);

        // Posting GRN pertama (12 diterima).
        $this->actingAs($user)->post(route('purchasing.grns.post', $grn->id))
            ->assertRedirect(route('purchasing.grns.show', $grn->id));

        $grnPayload = fn (float $qty, float $price) => [
            'branch_id' => $branchBId,
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

        // Harga beda dari PO ditolak.
        $this->actingAs($user)
            ->from(route('purchasing.invoices.create'))
            ->post(route('purchasing.invoices.store'), $grnPayload(12, 55000))
            ->assertSessionHasErrors(['items.0.unit_price']);

        // Tagih 12 sesuai GRN → approved + counter naik.
        $this->actingAs($user)->post(route('purchasing.invoices.store'), $grnPayload(12, 50000))
            ->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $this->assertEquals(12, (float) DB::table('goods_receipt_items')->where('id', $grnItem->id)->value('qty_invoiced'));
        $this->assertEquals(12, (float) DB::table('purchase_order_items')->where('id', $poItem->id)->value('qty_invoiced'));

        $inv = DB::table('purchase_invoices')->orderByDesc('id')->first();
        $this->assertSame($grn->id, (int) $inv->goods_receipt_id);
        $this->assertStringStartsWith('FBL/', (string) $inv->number);
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
        $warehouseId = DB::table('warehouses')->where('branch_id', $branchBId)->where('warehouse_type', 'regular')->value('id');

        $po = PurchaseOrder::create([
            'number' => 'PO-DUP-001',
            'branch_id' => $branchBId,
            'warehouse_id' => $warehouseId,
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
            'warehouse_id' => $warehouseId,
            'status' => GoodsReceiptStatus::Draft,
            'receipt_date' => now()->toDateString(),
        ]);
        $grnItemA = $grn->items()->create([
            'purchase_order_item_id' => $poItemA->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_received' => 300,
        ]);
        $grnItemB = $grn->items()->create([
            'purchase_order_item_id' => $poItemB->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_received' => 200,
        ]);
        tenancy()->end();

        $this->actingAs($user)->post(route('purchasing.grns.post', $grn->id))
            ->assertRedirect(route('purchasing.grns.show', $grn->id));

        $this->actingAs($user)->post(route('purchasing.invoices.store'), [
            'branch_id' => $branchBId,
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
                    'unit_price' => 50000,
                ],
                [
                    'purchase_order_item_id' => $poItemB->id,
                    'goods_receipt_item_id' => $grnItemB->id,
                    'product_variant_id' => $variantId,
                    'qty' => 200,
                    'unit_price' => 50000,
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
