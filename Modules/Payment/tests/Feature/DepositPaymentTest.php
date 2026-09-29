<?php

namespace Modules\Payment\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder;
use Modules\Accounting\Models\Journal;
use Modules\Payment\Application\Deposit\CreateDepositPayment;
use Modules\Payment\Models\PurchasePayment;
use Tests\TestCase;

class DepositPaymentTest extends TestCase
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

    public function test_deposit_records_remaining_and_posts_journal(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $payment = app(CreateDepositPayment::class)->execute([
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'cash_account_id' => $ctx['cashAccountId'],
            'amount' => 1000000,
            'payment_date' => '2026-09-24',
            'memo' => 'DP proyek X',
        ], null);

        $this->assertSame('approved', $payment->status);
        $this->assertSame('deposit', $payment->mode);
        $this->assertEquals(1000000, (float) $payment->deposit_total);
        $this->assertEquals(1000000, (float) $payment->deposit_remaining);

        $journal = Journal::where('reference_type', 'purchase_payment')
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $lines = DB::table('journal_lines')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.journal_id', $journal->id)
            ->select('chart_of_accounts.seed_key', 'journal_lines.debit', 'journal_lines.credit')
            ->get();

        $depositLine = $lines->firstWhere('seed_key', 'accounting.coa.1402');
        $cashLine = $lines->firstWhere('seed_key', 'accounting.coa.1101');

        $this->assertNotNull($depositLine, 'Akun 1402 (Uang Muka Pembelian) tidak ada di jurnal.');
        $this->assertEquals(1000000, (float) $depositLine->debit);
        $this->assertNotNull($cashLine);
        $this->assertEquals(1000000, (float) $cashLine->credit);

        tenancy()->end();
    }

    public function test_deposit_rejects_non_positive_amount(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        try {
            app(CreateDepositPayment::class)->execute([
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => $ctx['cashAccountId'],
                'amount' => 0,
            ], null);
            $this->fail('Expected ValidationException for zero amount.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->assertSame(0, PurchasePayment::count());

        tenancy()->end();
    }

    public function test_deposit_rejects_invalid_cash_account(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        try {
            app(CreateDepositPayment::class)->execute([
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => 999999,
                'amount' => 500000,
            ], null);
            $this->fail('Expected ValidationException for invalid account.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cash_account_id', $e->errors());
        }

        tenancy()->end();
    }

    public function test_deposit_rejects_non_hq_branch(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'B-'.uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(CreateDepositPayment::class)->execute([
                'branch_id' => $branchBId,
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => $ctx['cashAccountId'],
                'amount' => 500000,
            ], null);
            $this->fail('Expected ValidationException for non-HQ branch.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('branch_id', $e->errors());
        }

        tenancy()->end();
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('dep_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Deposit Test-'.$id,
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

        $supplierId = DB::table('contacts')->insertGetId([
            'branch_id' => $hqBranchId,
            'type' => 'supplier',
            'name' => 'Supplier '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cashAccountId = (int) DB::table('chart_of_accounts')->where('seed_key', 'accounting.coa.1101')->value('id');

        tenancy()->end();

        return [
            'tenantId' => $tenant->id,
            'hqBranchId' => (int) $hqBranchId,
            'supplierId' => (int) $supplierId,
            'cashAccountId' => $cashAccountId,
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
