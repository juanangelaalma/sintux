<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Purchasing\Application\PurchaseInvoice\GetInvoiceForGoodsReceipt;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Tests\TestCase;

class GetInvoiceForGoodsReceiptTest extends TestCase
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

    public function test_returns_invoice_for_goods_receipt(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);

        $result = app(GetInvoiceForGoodsReceipt::class)->execute($ctx['grnId']);

        $this->assertNotNull($result);
        $this->assertSame($ctx['invoiceId'], $result['id']);
        $this->assertSame('FBL/HQ/20260923/001/A', $result['number']);
        $this->assertSame(PurchaseInvoiceStatus::Approved->value, $result['status']);

        tenancy()->end();
    }

    public function test_returns_null_when_goods_receipt_has_no_invoice(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);

        $this->assertNull(app(GetInvoiceForGoodsReceipt::class)->execute($ctx['grnId'] + 999));

        tenancy()->end();
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('grninv_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'GRN Invoice Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);
        tenancy()->initialize($tenant);

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
            'code' => 'WH-GRNINV-'.uniqid(),
            'name' => 'GRN Invoice Warehouse',
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

        $poId = DB::table('purchase_orders')->insertGetId([
            'number' => 'PO-'.$id,
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'status' => 'received',
            'order_date' => '2026-09-01',
            'currency_code' => 'IDR',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $grnId = DB::table('goods_receipts')->insertGetId([
            'number' => 'GRN-'.$id,
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $poId,
            'warehouse_id' => $warehouseId,
            'supplier_do_no' => 'DO-'.$id,
            'status' => 'approved',
            'receipt_date' => '2026-09-02',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = DB::table('purchase_invoices')->insertGetId([
            'number' => 'FBL/HQ/20260923/001/A',
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $poId,
            'goods_receipt_id' => $grnId,
            'status' => PurchaseInvoiceStatus::Approved->value,
            'invoice_date' => '2026-09-23',
            'currency_code' => 'IDR',
            'is_tax_inclusive' => false,
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'returned_amount' => 0,
            'paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        return [
            'tenantId' => $tenant->id,
            'grnId' => (int) $grnId,
            'invoiceId' => (int) $invoiceId,
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
