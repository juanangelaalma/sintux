<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Application\PurchaseInvoice\ApplyInvoicePayment;
use Modules\Purchasing\Application\PurchaseInvoice\GetPayableInvoices;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Models\PurchaseInvoice;
use Tests\TestCase;

class PayableInvoiceTest extends TestCase
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

    public function test_get_payable_returns_only_invoices_with_outstanding(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $approved = $this->createInvoice($ctx, 100000, PurchaseInvoiceStatus::Approved, 0, 0);
        $this->createInvoice($ctx, 100000, PurchaseInvoiceStatus::Approved, 100000, 0); // paid
        $this->createInvoice($ctx, 100000, PurchaseInvoiceStatus::ClosedByReturn, 0, 100000); // closed by return
        $this->createInvoice($ctx, 100000, PurchaseInvoiceStatus::Approved, 60000, 40000); // fully settled by pay+return

        $rows = app(GetPayableInvoices::class)->execute($ctx['supplierId'], [$ctx['hqBranchId']]);

        $this->assertCount(1, $rows);
        $this->assertSame($approved, $rows[0]['id']);
        $this->assertSame(100000.0, $rows[0]['outstanding']);

        tenancy()->end();
    }

    public function test_apply_payment_rejects_amount_above_outstanding(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 100000, PurchaseInvoiceStatus::Approved, 0, 0);

        try {
            app(ApplyInvoicePayment::class)->execute($invoiceId, 100001);
            $this->fail('Expected ValidationException for overpay.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertEquals(0, (float) PurchaseInvoice::find($invoiceId)->paid_amount);

        tenancy()->end();
    }

    public function test_apply_payment_transitions_to_partially_paid_then_paid(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 100000, PurchaseInvoiceStatus::Approved, 0, 0);

        $invoice = app(ApplyInvoicePayment::class)->execute($invoiceId, 40000);
        $this->assertEquals(40000, (float) $invoice->paid_amount);
        $this->assertSame(PurchaseInvoiceStatus::PartiallyPaid->value, $invoice->status->value);

        $invoice = app(ApplyInvoicePayment::class)->execute($invoiceId, 60000);
        $this->assertEquals(100000, (float) $invoice->paid_amount);
        $this->assertSame(PurchaseInvoiceStatus::Paid->value, $invoice->status->value);

        tenancy()->end();
    }

    public function test_apply_payment_is_idempotent_per_reference(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 100000, PurchaseInvoiceStatus::Approved, 0, 0);

        app(ApplyInvoicePayment::class)->execute($invoiceId, 40000, 'purchase_payment', 9001);
        app(ApplyInvoicePayment::class)->execute($invoiceId, 40000, 'purchase_payment', 9001);

        $this->assertEquals(40000, (float) PurchaseInvoice::find($invoiceId)->paid_amount);

        tenancy()->end();
    }

    public function test_outstanding_accounts_for_returned_amount(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        // Total 100k, retur 30k → outstanding 70k meski belum ada bayar.
        $invoiceId = $this->createInvoice($ctx, 100000, PurchaseInvoiceStatus::PartiallyPaid, 0, 30000);

        try {
            app(ApplyInvoicePayment::class)->execute($invoiceId, 70001);
            $this->fail('Expected ValidationException for overpay beyond post-return outstanding.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        app(ApplyInvoicePayment::class)->execute($invoiceId, 70000);
        $this->assertSame(PurchaseInvoiceStatus::Paid->value, PurchaseInvoice::find($invoiceId)->status->value);

        tenancy()->end();
    }

    private function createInvoice(array $ctx, float $total, PurchaseInvoiceStatus $status, float $paid, float $returned): int
    {
        return DB::table('purchase_invoices')->insertGetId([
            'number' => 'FBL-'.uniqid(),
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'status' => $status->value,
            'invoice_date' => '2026-09-01',
            'currency_code' => 'IDR',
            'subtotal' => $total,
            'tax_amount' => 0,
            'total' => $total,
            'paid_amount' => $paid,
            'returned_amount' => $returned,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('pay_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Payable Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
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

        tenancy()->end();

        return [
            'tenantId' => $tenant->id,
            'hqBranchId' => (int) $hqBranchId,
            'supplierId' => (int) $supplierId,
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
