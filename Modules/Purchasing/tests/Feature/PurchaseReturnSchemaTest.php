<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Tests\TestCase;

class PurchaseReturnSchemaTest extends TestCase
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

    public function test_return_tables_and_tracking_columns_exist(): void
    {
        [$tenantId] = $this->createTenant();
        tenancy()->initialize($tenantId);

        foreach ([
            'purchase_returns',
            'purchase_return_items',
            'supplier_debit_memos',
            'purchase_return_attachments',
            'purchase_return_purchase_tag',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected tenant table [{$table}] to exist.");
        }

        $this->assertTrue(Schema::hasColumn('purchase_invoice_items', 'qty_returned'));
        $this->assertTrue(Schema::hasColumn('purchase_invoices', 'returned_amount'));
        $this->assertTrue(Schema::hasColumn('purchase_invoices', 'paid_amount'));

        tenancy()->end();
    }

    public function test_return_tracking_columns_have_zero_defaults(): void
    {
        [$tenantId, $hqBranchId] = $this->createTenantWithBranch();
        tenancy()->initialize($tenantId);

        $invoiceId = DB::table('purchase_invoices')->insertGetId([
            'number' => 'FBL/HQ/20260923/001/A',
            'branch_id' => $hqBranchId,
            'supplier_id' => DB::table('contacts')->insertGetId([
                'branch_id' => $hqBranchId,
                'type' => 'supplier',
                'name' => 'Supplier Schema',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]),
            'status' => 'approved',
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'IDR',
            'subtotal' => 100000,
            'tax_amount' => 0,
            'total' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inv = DB::table('purchase_invoices')->where('id', $invoiceId)->first();
        $this->assertEquals(0, (float) $inv->returned_amount);
        $this->assertEquals(0, (float) $inv->paid_amount);

        tenancy()->end();
    }

    public function test_invoice_status_supports_closed_by_return_and_partially_paid(): void
    {
        $this->assertSame('closed_by_return', PurchaseInvoiceStatus::ClosedByReturn->value);
        $this->assertSame('partially_paid', PurchaseInvoiceStatus::PartiallyPaid->value);
    }

    public function test_return_accounts_resolvable_by_seed_key(): void
    {
        [$tenantId] = $this->createTenant();
        tenancy()->initialize($tenantId);

        foreach (['accounting.coa.1301', 'accounting.coa.1402', 'accounting.coa.1404', 'accounting.coa.2101', 'accounting.coa.5201', 'accounting.coa.1398'] as $seedKey) {
            $this->assertTrue(
                DB::table('chart_of_accounts')->where('seed_key', $seedKey)->exists(),
                "Expected CoA seed_key [{$seedKey}] to exist."
            );
        }

        tenancy()->end();
    }

    public function test_purchase_return_approval_type_exists(): void
    {
        [$tenantId] = $this->createTenant();
        tenancy()->initialize($tenantId);

        $this->assertTrue(
            DB::table('approval_transaction_types')->where('key', 'purchase_return')->exists(),
            'Expected approval type [purchase_return] to exist.'
        );

        tenancy()->end();
    }

    /**
     * @return array{0: string}
     */
    private function createTenant(): array
    {
        $id = uniqid('ret_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Return Schema Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        return [$tenant->id];
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function createTenantWithBranch(): array
    {
        [$tenantId] = $this->createTenant();
        tenancy()->initialize($tenantId);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        return [$tenantId, (int) $hqBranchId];
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
