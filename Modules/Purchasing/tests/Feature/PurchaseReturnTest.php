<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Tax;
use Modules\Approval\Application\CreateApprovalRule;
use Modules\Approval\Models\ApprovalMapping;
use Modules\Approval\Models\ApprovalTransactionType;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Application\PurchaseReturn\CreatePurchaseReturn;
use Modules\Purchasing\Application\PurchaseReturn\GetReturnableItems;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Models\PurchaseReturn;
use Modules\Warehouse\Models\StockBalance;
use Tests\TestCase;

class PurchaseReturnTest extends TestCase
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

    public function test_create_rejects_qty_above_remaining(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        try {
            $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 11]]);
            $this->fail('Expected ValidationException for over-qty.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(0, PurchaseReturn::query()->count());

        tenancy()->end();
    }

    public function test_create_rejects_qty_above_available_stock(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Simulasi barang sudah berpindah gudang: sisa faktur 10, stok 3.
        DB::table('stock_balances')
            ->where('warehouse_id', $ctx['warehouseId'])
            ->where('product_variant_id', $ctx['trackedVariantId'])
            ->update(['qty_on_hand' => 3]);

        try {
            $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 5]]);
            $this->fail('Expected ValidationException for insufficient stock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.qty', $e->errors());
        }

        $this->assertSame(0, PurchaseReturn::query()->count());

        tenancy()->end();
    }

    public function test_create_rejects_non_returnable_invoice(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        foreach ([PurchaseInvoiceStatus::Paid->value, PurchaseInvoiceStatus::ClosedByReturn->value] as $status) {
            DB::table('purchase_invoices')->where('id', $ctx['invoiceId'])->update(['status' => $status]);

            try {
                $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 1]]);
                $this->fail("Expected ValidationException for status [{$status}].");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('purchase_invoice_id', $e->errors());
            }
        }

        tenancy()->end();
    }

    public function test_create_rejects_non_hq_branch(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB',
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(CreatePurchaseReturn::class)->execute(
                $this->payload($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 1]], ['branch_id' => $branchBId]),
                'BRB',
                $ctx['user']->id,
                $ctx['user']->name,
                [$ctx['hqBranchId'], $branchBId]
            );
            $this->fail('Expected ValidationException for non-HQ branch.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('branch_id', $e->errors());
        }

        tenancy()->end();
    }

    public function test_return_numbers_sequence_per_invoice(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);
        $this->createApprovalRule($ctx);

        $first = $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 2]]);
        $second = $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 3]]);

        $this->assertSame('RBL-FBLHQ20260923001A-01', $first->number);
        $this->assertSame('RBL-FBLHQ20260923001A-02', $second->number);

        tenancy()->end();
    }

    public function test_create_computes_totals_and_snapshots(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);
        $this->createApprovalRule($ctx);

        // 2 @ 50000 + PPN 11/12×12% (11000) = 111000.
        $purchaseReturn = $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 2]]);

        $this->assertEquals(100000, (float) $purchaseReturn->subtotal);
        $this->assertEquals(11000, (float) $purchaseReturn->tax_amount);
        $this->assertEquals(111000, (float) $purchaseReturn->total);

        $item = $purchaseReturn->items()->firstOrFail();
        $this->assertSame('Widget TRK', $item->product_name);
        $this->assertSame('SKU-TRK', $item->sku);
        $this->assertEquals(50000, (float) $item->unit_price);
        $this->assertEquals(111000, (float) $item->line_total);

        tenancy()->end();
    }

    public function test_create_with_rule_stays_pending_without_effects(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);
        $this->createApprovalRule($ctx);

        $purchaseReturn = $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 10]]);

        $this->assertSame('pending', $purchaseReturn->status);
        $this->assertNotNull(ApprovalMapping::where('transaction_type', 'purchase_return')
            ->where('transaction_id', $purchaseReturn->id)
            ->first());
        $this->assertEquals(10, (int) StockBalance::query()
            ->where('warehouse_id', $ctx['warehouseId'])
            ->where('product_variant_id', $ctx['trackedVariantId'])
            ->value('qty_on_hand'));
        $this->assertSame(0, Journal::query()->count());
        $this->assertEquals(0, (float) DB::table('purchase_invoices')->where('id', $ctx['invoiceId'])->value('returned_amount'));

        tenancy()->end();
    }

    public function test_create_without_rule_auto_finalizes(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $purchaseReturn = $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['untrackedItemId'], 'qty' => 5]]);

        $this->assertSame('approved', $purchaseReturn->fresh()->status);
        $this->assertSame(1, Journal::query()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', $purchaseReturn->id)
            ->count());
        $this->assertEquals(100000, (float) DB::table('purchase_invoices')->where('id', $ctx['invoiceId'])->value('returned_amount'));

        tenancy()->end();
    }

    public function test_approval_finalizes_return_on_approve(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);
        $this->createApprovalRule($ctx, $ctx['approver']->id);
        // Total 555000 > threshold 100000 agar masuk jalur approval (aturan: total > min).
        $purchaseReturn = $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 10]]);
        $mappingId = ApprovalMapping::where('transaction_type', 'purchase_return')
            ->where('transaction_id', $purchaseReturn->id)
            ->value('id');
        $this->assertNotNull($mappingId);
        tenancy()->end();

        session(['active_tenant_id' => $ctx['tenantId'], 'active_branch_id' => $ctx['hqBranchId']]);
        $this->actingAs($ctx['approver'])->post(route('approval.mappings.approve', $mappingId), [])
            ->assertSessionHasNoErrors();

        tenancy()->initialize($ctx['tenantId']);
        $this->assertSame('approved', PurchaseReturn::find($purchaseReturn->id)->status);
        $this->assertSame(1, Journal::query()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', $purchaseReturn->id)
            ->count());
        tenancy()->end();
    }

    public function test_returnable_items_show_remaining_qty(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Auto-final: retur 4 dari 10 → sisa 6.
        $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 4]]);

        $detail = app(GetReturnableItems::class)->execute($ctx['invoiceId']);
        $tracked = collect($detail['items'])->firstWhere('purchase_invoice_item_id', $ctx['trackedItemId']);

        $this->assertEquals(10, (float) $tracked['qty']);
        $this->assertEquals(4, (float) $tracked['qty_returned']);
        $this->assertEquals(6, (float) $tracked['remaining_qty']);
        $this->assertTrue($tracked['is_tracked']);

        tenancy()->end();
    }

    public function test_create_with_unreceived_transfer_is_rejected_despite_ho_stock(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Transfer cabang→HO dibuat tapi BELUM di-receive: tak ada layer.
        // Stok HO 10 pcs produk sama wajib diabaikan (aturan provenance).
        $transferId = $this->createTransfer($ctx, 'shipped');

        try {
            $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 2]], [
                'return_transfer_id' => $transferId,
            ]);
            $this->fail('Expected ValidationException for unreceived transfer.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.qty', $e->errors());
        }

        $this->assertSame(0, PurchaseReturn::query()->count());

        tenancy()->end();
    }

    public function test_create_with_received_transfer_consumes_only_transfer_layers(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Layer transfer 6 @ 52000; stok reguler HO 10 @ 50000 tetap ada.
        $transferId = $this->createTransfer($ctx, 'received');
        $this->addTransferLayer($ctx['warehouseId'], $ctx['trackedVariantId'], 6, 52000, $transferId);

        // Tanpa rule → auto-final: 4 pcs @ DO 50000 + PPN.
        $purchaseReturn = $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 4]], [
            'return_transfer_id' => $transferId,
        ]);

        $this->assertSame('approved', $purchaseReturn->fresh()->status);
        $this->assertSame($transferId, (int) $purchaseReturn->fresh()->return_transfer_id);

        // Layer reguler utuh 10; layer transfer sisa 2.
        $this->assertEquals(10, (float) DB::table('stock_layers')
            ->where('warehouse_id', $ctx['warehouseId'])
            ->where('product_variant_id', $ctx['trackedVariantId'])
            ->where('source_type', 'purchase_order')
            ->sum('qty_remaining'));
        $this->assertEquals(2, (float) DB::table('stock_layers')
            ->where('warehouse_id', $ctx['warehouseId'])
            ->where('product_variant_id', $ctx['trackedVariantId'])
            ->where('source_type', 'stock_transfer')
            ->sum('qty_remaining'));

        tenancy()->end();
    }

    public function test_create_rejects_transfer_to_other_warehouse(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $transferId = $this->createTransfer($ctx, 'received', true);

        try {
            $this->createReturn($ctx, [['purchase_invoice_item_id' => $ctx['trackedItemId'], 'qty' => 1]], [
                'return_transfer_id' => $transferId,
            ]);
            $this->fail('Expected ValidationException for mismatched warehouse.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('return_transfer_id', $e->errors());
        }

        tenancy()->end();
    }

    private function createTransfer(array $ctx, string $status, bool $otherWarehouse = false): int
    {
        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch TF',
            'code' => 'BTF_'.uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchWhId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-TF-'.uniqid(),
            'name' => 'TF Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => null,
            'from_warehouse_id' => $branchWhId,
            'to_warehouse_id' => $otherWarehouse ? $branchWhId : $ctx['warehouseId'],
            'number' => 'TRF/'.uniqid(),
            'status' => $status,
            'created_by' => $ctx['user']->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addTransferLayer(int $warehouseId, int $variantId, float $qty, float $unitCost, int $transferId): void
    {
        DB::table('stock_layers')->insert([
            'product_variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'qty_remaining' => $qty,
            'unit_cost' => $unitCost,
            'received_at' => now(),
            'source_type' => 'stock_transfer',
            'source_id' => $transferId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_balances')
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->increment('qty_on_hand', $qty);
    }

    /**
     * @param  list<array{purchase_invoice_item_id: int, qty: float}>  $items
     * @param  array<string, mixed>  $overrides
     */
    private function createReturn(array $ctx, array $items, array $overrides = []): PurchaseReturn
    {
        return app(CreatePurchaseReturn::class)->execute(
            $this->payload($ctx, $items, $overrides),
            'HQ',
            $ctx['user']->id,
            $ctx['user']->name,
            [$ctx['hqBranchId']]
        );
    }

    /**
     * @param  list<array{purchase_invoice_item_id: int, qty: float}>  $items
     * @return array<string, mixed>
     */
    private function payload(array $ctx, array $items, array $overrides = []): array
    {
        return array_merge([
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'purchase_invoice_id' => $ctx['invoiceId'],
            'warehouse_id' => $ctx['warehouseId'],
            'return_date' => '2026-09-23',
            'message' => 'Barang cacat',
            'memo' => 'Memo retur',
            'tag_ids' => [],
            'items' => $items,
        ], $overrides);
    }

    private function createApprovalRule(array $ctx, ?int $approverId = null): void
    {
        $type = ApprovalTransactionType::where('key', 'purchase_return')->firstOrFail();

        app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $type->id,
            'name' => 'Return Rule',
            'min_amount' => 100000,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$approverId ?? $ctx['user']->id + 999]],
            ],
        ], $ctx['user']->id);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('crt_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Create Return Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);
        $this->seed(ChartOfAccountsSeeder::class);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-CRT-'.uniqid(),
            'name' => 'Create Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supplierId = DB::table('contacts')->insertGetId([
            'branch_id' => $hqBranchId,
            'type' => 'supplier',
            'name' => 'Supplier '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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

        $makeProduct = function (bool $tracked, string $suffix) use ($hqBranchId, $catId, $uomId): array {
            $productId = DB::table('products')->insertGetId([
                'branch_id' => $hqBranchId,
                'code' => 'PRD-'.$suffix.'-'.uniqid(),
                'name' => 'Widget '.$suffix,
                'category_id' => $catId,
                'uom_id' => $uomId,
                'is_inventory_tracked' => $tracked,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $variantId = DB::table('product_variants')->insertGetId([
                'branch_id' => $hqBranchId,
                'product_id' => $productId,
                'sku' => $suffix === 'TRK' ? 'SKU-TRK' : 'SKU-UTRK',
                'variant_name' => 'Widget '.$suffix,
                'attributes' => json_encode(['color' => 'blue']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [$productId, $variantId];
        };

        [, $trackedVariantId] = $makeProduct(true, 'TRK');
        [, $untrackedVariantId] = $makeProduct(false, 'UTRK');

        DB::table('stock_balances')->insert([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $trackedVariantId,
            'qty_on_hand' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_layers')->insert([
            'product_variant_id' => $trackedVariantId,
            'warehouse_id' => $warehouseId,
            'qty_remaining' => 10,
            'unit_cost' => 50000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ppnId = Tax::where('code', 'PPN')->value('id');

        $invoiceId = DB::table('purchase_invoices')->insertGetId([
            'number' => 'FBL/HQ/20260923/001/A',
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'status' => PurchaseInvoiceStatus::Approved->value,
            'invoice_date' => '2026-09-23',
            'currency_code' => 'IDR',
            'is_tax_inclusive' => false,
            'subtotal' => 600000,
            'tax_amount' => 55000,
            'total' => 655000,
            'returned_amount' => 0,
            'paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $trackedItemId = DB::table('purchase_invoice_items')->insertGetId([
            'purchase_invoice_id' => $invoiceId,
            'product_variant_id' => $trackedVariantId,
            'product_name' => 'Widget Tracked',
            'sku' => 'SKU-TRK',
            'uom_name' => 'PCS',
            'qty' => 10,
            'qty_returned' => 0,
            'unit_price' => 50000,
            'tax_id' => $ppnId,
            'tax_rate' => 12,
            'line_total' => 555000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $untrackedItemId = DB::table('purchase_invoice_items')->insertGetId([
            'purchase_invoice_id' => $invoiceId,
            'product_variant_id' => $untrackedVariantId,
            'product_name' => 'Widget Untracked',
            'sku' => 'SKU-UTRK',
            'uom_name' => 'PCS',
            'qty' => 5,
            'qty_returned' => 0,
            'unit_price' => 20000,
            'tax_id' => null,
            'tax_rate' => 0,
            'line_total' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'creator_'.$id.'@acme.test',
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

        $approver = User::factory()->create([
            'email' => 'approver_'.$id.'@acme.test',
            'role' => 'user',
        ]);

        $approverMember = CompanyUser::create([
            'user_id' => $approver->id,
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'is_default' => false,
        ]);

        DB::table('company_user_branches')->insert([
            'company_user_id' => $approverMember->id,
            'branch_id' => $hqBranchId,
        ]);

        return [
            'tenantId' => $tenant->id,
            'hqBranchId' => (int) $hqBranchId,
            'warehouseId' => (int) $warehouseId,
            'supplierId' => (int) $supplierId,
            'trackedVariantId' => (int) $trackedVariantId,
            'untrackedVariantId' => (int) $untrackedVariantId,
            'invoiceId' => (int) $invoiceId,
            'trackedItemId' => (int) $trackedItemId,
            'untrackedItemId' => (int) $untrackedItemId,
            'user' => $user,
            'approver' => $approver,
        ];
    }

    private function cleanupCentralTables(): void
    {
        foreach (['company_user_branches', 'company_users', 'tenants', 'users'] as $table) {
            try {
                DB::table($table)->delete();
            } catch (\Throwable $e) {
            }
        }
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
