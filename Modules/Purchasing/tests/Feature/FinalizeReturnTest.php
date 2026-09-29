<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder;
use Modules\Accounting\Models\Journal;
use Modules\Accounting\Models\Tax;
use Modules\Purchasing\Application\PurchaseReturn\FinalizePurchaseReturn;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Models\PurchaseReturn;
use Modules\Warehouse\Application\StockLayer\ReceivePurchaseStock;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockMovement;
use Tests\TestCase;

class FinalizeReturnTest extends TestCase
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

    public function test_finalize_tracked_item_posts_stock_journal_and_partial_status(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Retur penuh baris tracked: 10 @ 50000 + PPN 55000 = 555000.
        $returnId = $this->createReturn($ctx, 'RBL-TEST-01', [
            ['key' => 'tracked', 'qty' => 10],
        ]);

        app(FinalizePurchaseReturn::class)->execute($returnId);

        $this->assertSame('approved', PurchaseReturn::find($returnId)->status);
        $this->assertEquals(0, (int) StockBalance::query()
            ->where('warehouse_id', $ctx['warehouseId'])
            ->where('product_variant_id', $ctx['trackedVariantId'])
            ->value('qty_on_hand'));
        $this->assertSame(1, StockMovement::query()
            ->where('movement_type', 'purchase_return_out')
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', $returnId)
            ->count());

        // Dr Hutang 555000 / Cr Persediaan 500000 / Cr PPN 55000 (tanpa selisih).
        $this->assertJournalLegs($returnId, [
            'accounting.coa.2101' => [555000, 0],
            'accounting.coa.1301' => [0, 500000],
            'accounting.coa.1404' => [0, 55000],
        ]);

        $inv = DB::table('purchase_invoices')->where('id', $ctx['invoiceId'])->first();
        $this->assertEquals(10, (float) DB::table('purchase_invoice_items')->where('id', $ctx['trackedItemId'])->value('qty_returned'));
        $this->assertEquals(555000, (float) $inv->returned_amount);
        $this->assertSame(PurchaseInvoiceStatus::PartiallyPaid->value, $inv->status);
        $this->assertSame(0, DB::table('supplier_debit_memos')->count());

        tenancy()->end();
    }

    public function test_finalize_untracked_item_posts_expense_without_stock(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Retur penuh baris untracked: 5 @ 20000 = 100000, tanpa pajak & stok.
        $returnId = $this->createReturn($ctx, 'RBL-TEST-02', [
            ['key' => 'untracked', 'qty' => 5],
        ]);

        app(FinalizePurchaseReturn::class)->execute($returnId);

        $this->assertSame('approved', PurchaseReturn::find($returnId)->status);
        $this->assertSame(0, StockMovement::query()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', $returnId)
            ->count());

        $this->assertJournalLegs($returnId, [
            'accounting.coa.2101' => [100000, 0],
            'accounting.coa.5201' => [0, 100000],
        ]);

        tenancy()->end();
    }

    public function test_finalize_fifo_difference_goes_to_adjustment_account(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Layer stok @ 48000 vs harga DO 50000: selisih +20000 → Cr 1398.
        DB::table('stock_layers')->where('product_variant_id', $ctx['trackedVariantId'])->delete();
        DB::table('stock_balances')
            ->where('warehouse_id', $ctx['warehouseId'])
            ->where('product_variant_id', $ctx['trackedVariantId'])
            ->update(['qty_on_hand' => 0]);
        StockMovement::query()->delete();

        app(ReceivePurchaseStock::class)->execute([
            'warehouse_id' => $ctx['warehouseId'],
            'product_variant_id' => $ctx['trackedVariantId'],
            'qty' => 10,
            'unit_cost' => 48000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 9,
        ]);

        $returnId = $this->createReturn($ctx, 'RBL-TEST-03', [
            ['key' => 'tracked', 'qty' => 10],
        ]);

        app(FinalizePurchaseReturn::class)->execute($returnId);

        $this->assertJournalLegs($returnId, [
            'accounting.coa.2101' => [555000, 0],
            'accounting.coa.1301' => [0, 480000],
            'accounting.coa.1404' => [0, 55000],
            'accounting.coa.1398' => [0, 20000],
        ]);

        tenancy()->end();
    }

    public function test_finalize_excess_over_outstanding_creates_debit_memo(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Simulasi modul Payment: faktur sudah dibayar 600000.
        DB::table('purchase_invoices')->where('id', $ctx['invoiceId'])->update(['paid_amount' => 600000]);

        // Retur tracked 555000 vs outstanding 55000 → applied 55000, excess 500000.
        $returnId = $this->createReturn($ctx, 'RBL-TEST-04', [
            ['key' => 'tracked', 'qty' => 10],
        ]);

        app(FinalizePurchaseReturn::class)->execute($returnId);

        // Dr Hutang 55000 + Dr 1402 500000 / Cr Persediaan 500000 / Cr PPN 55000.
        $this->assertJournalLegs($returnId, [
            'accounting.coa.2101' => [55000, 0],
            'accounting.coa.1402' => [500000, 0],
            'accounting.coa.1301' => [0, 500000],
            'accounting.coa.1404' => [0, 55000],
        ]);

        $memo = DB::table('supplier_debit_memos')->where('purchase_return_id', $returnId)->first();
        $this->assertNotNull($memo);
        $this->assertEquals(500000, (float) $memo->total);
        $this->assertEquals(500000, (float) $memo->remaining);
        $this->assertStringStartsWith('DM/', (string) $memo->number);

        $inv = DB::table('purchase_invoices')->where('id', $ctx['invoiceId'])->first();
        $this->assertSame(PurchaseInvoiceStatus::Paid->value, $inv->status);

        tenancy()->end();
    }

    public function test_finalize_is_idempotent(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $returnId = $this->createReturn($ctx, 'RBL-TEST-05', [
            ['key' => 'untracked', 'qty' => 5],
        ]);

        app(FinalizePurchaseReturn::class)->execute($returnId);
        app(FinalizePurchaseReturn::class)->execute($returnId);

        $this->assertSame(1, Journal::query()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', $returnId)
            ->count());
        $this->assertEquals(100000, (float) DB::table('purchase_invoices')->where('id', $ctx['invoiceId'])->value('returned_amount'));

        tenancy()->end();
    }

    public function test_finalize_full_return_without_payment_closes_invoice_by_return(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Retur penuh kedua baris = total faktur 655000 → ClosedByReturn.
        $returnId = $this->createReturn($ctx, 'RBL-TEST-07', [
            ['key' => 'tracked', 'qty' => 10],
            ['key' => 'untracked', 'qty' => 5],
        ]);

        app(FinalizePurchaseReturn::class)->execute($returnId);

        $inv = DB::table('purchase_invoices')->where('id', $ctx['invoiceId'])->first();
        $this->assertSame(PurchaseInvoiceStatus::ClosedByReturn->value, $inv->status);
        $this->assertEquals(655000, (float) $inv->returned_amount);

        // Campuran tracked+untracked: 2101 / 1301 + 5201 / 1404.
        $this->assertJournalLegs($returnId, [
            'accounting.coa.2101' => [655000, 0],
            'accounting.coa.1301' => [0, 500000],
            'accounting.coa.5201' => [0, 100000],
            'accounting.coa.1404' => [0, 55000],
        ]);

        tenancy()->end();
    }

    public function test_finalize_inclusive_tax_uses_stored_breakdown(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Faktur inklusif: gross 111000 = net 100000 + pajak 11000.
        DB::table('purchase_invoices')->where('id', $ctx['invoiceId'])->update(['is_tax_inclusive' => true]);
        $returnId = DB::table('purchase_returns')->insertGetId([
            'number' => 'RBL-TEST-08',
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'purchase_invoice_id' => $ctx['invoiceId'],
            'warehouse_id' => $ctx['warehouseId'],
            'status' => 'pending',
            'return_date' => '2026-09-23',
            'currency_code' => 'IDR',
            'is_tax_inclusive' => true,
            'subtotal' => 100000,
            'tax_amount' => 11000,
            'total' => 111000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('purchase_return_items')->insert([
            'purchase_return_id' => $returnId,
            'purchase_invoice_item_id' => $ctx['trackedItemId'],
            'product_variant_id' => $ctx['trackedVariantId'],
            'product_name' => 'Widget Tracked',
            'sku' => 'SKU-TRK',
            'qty' => 2,
            'unit_price' => 55500,
            'tax_id' => $ctx['ppnId'],
            'tax_rate' => 12,
            'tax_breakdown' => json_encode([['tax_id' => $ctx['ppnId'], 'rate' => 12.0, 'amount' => 11000.0]]),
            'line_total' => 111000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(FinalizePurchaseReturn::class)->execute($returnId);

        // Hutang gross 111000; persediaan FIFO 2×50000=100000; PPN 11000.
        $this->assertJournalLegs($returnId, [
            'accounting.coa.2101' => [111000, 0],
            'accounting.coa.1301' => [0, 100000],
            'accounting.coa.1404' => [0, 11000],
        ]);

        tenancy()->end();
    }

    public function test_finalize_uses_stored_tax_snapshot_when_master_rate_changes(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Retur tracked 10 @ 50000 + PPN 55000 dibuat saat tarif 12%.
        $returnId = $this->createReturn($ctx, 'RBL-TEST-06', [
            ['key' => 'tracked', 'qty' => 10],
        ]);

        // Tarif master diubah menjadi 20% sebelum approval/finalize.
        DB::table('taxes')->where('id', $ctx['ppnId'])->update(['rate' => 20]);

        app(FinalizePurchaseReturn::class)->execute($returnId);

        // Jurnal tetap pakai snapshot 55000, bukan tarif baru.
        $this->assertJournalLegs($returnId, [
            'accounting.coa.2101' => [555000, 0],
            'accounting.coa.1301' => [0, 500000],
            'accounting.coa.1404' => [0, 55000],
        ]);

        tenancy()->end();
    }

    /**
     * @param  list<array{debit: float, credit: float}>  $expected  seed_key => [debit, credit]
     */
    private function assertJournalLegs(int $returnId, array $expected): void
    {
        $journal = Journal::query()
            ->where('reference_type', 'purchase_return')
            ->where('reference_id', $returnId)
            ->firstOrFail();

        $lines = DB::table('journal_lines')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.journal_id', $journal->id)
            ->select('chart_of_accounts.seed_key', 'journal_lines.debit', 'journal_lines.credit')
            ->get();

        $this->assertCount(count($expected), $lines, 'Jumlah kaki jurnal tidak sesuai.');

        foreach ($expected as $seedKey => [$debit, $credit]) {
            $line = $lines->firstWhere('seed_key', $seedKey);
            $this->assertNotNull($line, "Kaki jurnal [{$seedKey}] tidak ditemukan.");
            $this->assertEquals($debit, (float) $line->debit, "Debit [{$seedKey}] salah.");
            $this->assertEquals($credit, (float) $line->credit, "Kredit [{$seedKey}] salah.");
        }
    }

    /**
     * Konteks: tenant + HQ + gudang + supplier + CoA/PPN + produk
     * tracked (10 @ 50000, PPN) & untracked (5 @ 20000) + stok 10 pcs
     * + faktur approved total 655000.
     *
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('fin_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Finalize Return Test-'.$id,
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
            'code' => 'WH-FIN-'.uniqid(),
            'name' => 'Finalize Warehouse',
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

        $makeProduct = function (bool $tracked) use ($hqBranchId, $catId, $uomId): array {
            $productId = DB::table('products')->insertGetId([
                'branch_id' => $hqBranchId,
                'code' => 'PRD-'.uniqid(),
                'name' => 'Widget '.uniqid(),
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
                'sku' => 'SKU-'.uniqid(),
                'variant_name' => 'Variant '.uniqid(),
                'attributes' => json_encode(['color' => 'blue']),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return [$productId, $variantId];
        };

        [, $trackedVariantId] = $makeProduct(true);
        [, $untrackedVariantId] = $makeProduct(false);

        app(ReceivePurchaseStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $trackedVariantId,
            'qty' => 10,
            'unit_cost' => 50000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
        ]);

        $ppnId = Tax::where('code', 'PPN')->value('id');

        // Faktur: tracked 10 @ 50000 + PPN 55000 (= 555000) + untracked 5 @ 20000 (= 100000).
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
            'ppnId' => (int) $ppnId,
        ];
    }

    /**
     * @param  list<array{key: string, qty: float}>  $lines
     */
    private function createReturn(array $ctx, string $number, array $lines): int
    {
        $subtotal = 0.0;
        $taxAmount = 0.0;

        $returnId = DB::table('purchase_returns')->insertGetId([
            'number' => $number,
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'purchase_invoice_id' => $ctx['invoiceId'],
            'warehouse_id' => $ctx['warehouseId'],
            'status' => 'pending',
            'return_date' => '2026-09-23',
            'currency_code' => 'IDR',
            'is_tax_inclusive' => false,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($lines as $line) {
            $isTracked = $line['key'] === 'tracked';
            $itemId = $isTracked ? $ctx['trackedItemId'] : $ctx['untrackedItemId'];
            $variantId = $isTracked ? $ctx['trackedVariantId'] : $ctx['untrackedVariantId'];
            $unitPrice = $isTracked ? 50000 : 20000;
            $gross = $line['qty'] * $unitPrice;
            $tax = $isTracked ? round($gross * 11 / 12 * 0.12, 2) : 0.0;

            DB::table('purchase_return_items')->insert([
                'purchase_return_id' => $returnId,
                'purchase_invoice_item_id' => $itemId,
                'product_variant_id' => $variantId,
                'product_name' => $isTracked ? 'Widget Tracked' : 'Widget Untracked',
                'sku' => $isTracked ? 'SKU-TRK' : 'SKU-UTRK',
                'qty' => $line['qty'],
                'unit_price' => $unitPrice,
                'tax_id' => $isTracked ? $ctx['ppnId'] : null,
                'tax_rate' => $isTracked ? 12 : 0,
                'tax_breakdown' => $isTracked
                    ? json_encode([['tax_id' => $ctx['ppnId'], 'rate' => 12.0, 'amount' => $tax]])
                    : null,
                'line_total' => $gross + $tax,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $subtotal += $gross;
            $taxAmount += $tax;
        }

        DB::table('purchase_returns')->where('id', $returnId)->update([
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'total' => $subtotal + $taxAmount,
        ]);

        return $returnId;
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
