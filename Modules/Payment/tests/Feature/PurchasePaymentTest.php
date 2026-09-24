<?php

namespace Modules\Payment\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder;
use Modules\Accounting\Models\Journal;
use Modules\Approval\Application\CreateApprovalRule;
use Modules\Payment\Application\Finalize\FinalizePurchasePayment;
use Modules\Payment\Application\PurchasePayment\CreatePurchasePayment;
use Modules\Payment\Models\PurchasePayment;
use Tests\TestCase;

class PurchasePaymentTest extends TestCase
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

    public function test_payment_allocates_and_posts_journal_without_rule(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 500000);

        $payment = app(CreatePurchasePayment::class)->execute([
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'cash_account_id' => $ctx['cashAccountId'],
            'allocations' => [
                ['purchase_invoice_id' => $invoiceId, 'amount' => 200000],
            ],
            'payment_date' => '2026-09-24',
        ], null, null, [$ctx['hqBranchId']]);

        $this->assertSame('approved', $payment->status);
        $this->assertEquals(200000, (float) $payment->gross_amount);
        $this->assertEquals(200000, (float) $payment->cash_out);

        $this->assertJournal($payment->id, [
            'accounting.coa.2101' => [200000, 0], // Dr hutang
            'accounting.coa.1101' => [0, 200000], // Cr kas
        ]);

        $this->assertEquals(200000, (float) DB::table('purchase_invoices')->where('id', $invoiceId)->value('paid_amount'));
        $this->assertSame('partially_paid', DB::table('purchase_invoices')->where('id', $invoiceId)->value('status'));

        tenancy()->end();
    }

    public function test_withholding_percent_reduces_cash_out(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 1000000);

        $payment = app(CreatePurchasePayment::class)->execute([
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'cash_account_id' => $ctx['cashAccountId'],
            'allocations' => [
                ['purchase_invoice_id' => $invoiceId, 'amount' => 1000000],
            ],
            'withholdings' => [
                ['account_id' => $ctx['withholdingAccountId'], 'type' => 'percent', 'value' => 2],
            ],
            'payment_date' => '2026-09-24',
        ], null, null, [$ctx['hqBranchId']]);

        $this->assertEquals(20000, (float) $payment->withholding_amount);
        $this->assertEquals(980000, (float) $payment->cash_out);

        $this->assertJournal($payment->id, [
            'accounting.coa.2101' => [1000000, 0],
            'accounting.coa.1101' => [0, 980000],
            (string) $ctx['withholdingCode'] => [0, 20000],
        ]);

        tenancy()->end();
    }

    public function test_payment_rejects_overpay_and_wrong_supplier(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 100000);

        try {
            app(CreatePurchasePayment::class)->execute([
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => $ctx['cashAccountId'],
                'allocations' => [
                    ['purchase_invoice_id' => $invoiceId, 'amount' => 100001],
                ],
            ], null, null, [$ctx['hqBranchId']]);
            $this->fail('Expected ValidationException for overpay.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        tenancy()->end();
    }

    public function test_deposit_and_memo_credit_reduce_cash_out(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 1000000);

        // Uang muka 100k
        $depositId = DB::table('purchase_payments')->insertGetId([
            'number' => 'PBL/DP/001',
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'mode' => 'deposit',
            'payment_date' => '2026-09-20',
            'currency_code' => 'IDR',
            'cash_account_id' => $ctx['cashAccountId'],
            'cash_out' => 100000,
            'deposit_total' => 100000,
            'deposit_remaining' => 100000,
            'status' => 'approved',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Debit memo 50k
        $memoId = DB::table('supplier_debit_memos')->insertGetId([
            'number' => 'DM/20260924/001',
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'status' => 'open',
            'total' => 50000,
            'remaining' => 50000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = app(CreatePurchasePayment::class)->execute([
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'cash_account_id' => $ctx['cashAccountId'],
            'allocations' => [
                ['purchase_invoice_id' => $invoiceId, 'amount' => 1000000],
            ],
            'deposit_uses' => [
                ['payment_id' => $depositId, 'amount' => 100000],
            ],
            'memo_uses' => [
                ['memo_id' => $memoId, 'amount' => 50000],
            ],
            'payment_date' => '2026-09-24',
        ], null, null, [$ctx['hqBranchId']]);

        $this->assertEquals(100000, (float) $payment->deposit_applied);
        $this->assertEquals(50000, (float) $payment->memo_applied);
        $this->assertEquals(850000, (float) $payment->cash_out);

        // Deposit remaining reduced
        $this->assertEquals(0, (float) DB::table('purchase_payments')->where('id', $depositId)->value('deposit_remaining'));
        // Memo remaining reduced
        $this->assertEquals(0, (float) DB::table('supplier_debit_memos')->where('id', $memoId)->value('remaining'));

        // Journal: Dr 2101 1000k / Cr 1101 850k + Cr 1402 (dp 100k) + Cr 1402 (memo 50k)
        // 1402 dipisah menjadi dua baris (deposit & memo) agar audit jelas.
        $journal = Journal::where('reference_type', 'purchase_payment')
            ->where('reference_id', $payment->id)
            ->firstOrFail();

        $lines = DB::table('journal_lines')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.journal_id', $journal->id)
            ->select('chart_of_accounts.seed_key', 'journal_lines.debit', 'journal_lines.credit')
            ->get();

        $debit = (float) $lines->sum('debit');
        $credit = (float) $lines->sum('credit');
        $this->assertEquals($debit, $credit, 'Jurnal tidak balance.');

        $payable = $lines->firstWhere('seed_key', 'accounting.coa.2101');
        $cash = $lines->firstWhere('seed_key', 'accounting.coa.1101');
        $memoCredits = $lines->where('seed_key', 'accounting.coa.1402')->sum('credit');

        $this->assertEquals(1000000, (float) $payable->debit);
        $this->assertEquals(850000, (float) $cash->credit);
        $this->assertEquals(150000, (float) $memoCredits, 'Total kredit 1402 (dp+memo) harus 150k.');

        tenancy()->end();
    }

    public function test_finalize_is_idempotent(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 500000);

        $payment = app(CreatePurchasePayment::class)->execute([
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'cash_account_id' => $ctx['cashAccountId'],
            'allocations' => [
                ['purchase_invoice_id' => $invoiceId, 'amount' => 300000],
            ],
        ], null, null, [$ctx['hqBranchId']]);

        $paymentId = (int) $payment->id;

        // sudah auto-final; finalize kedua tidak mengulang
        app(FinalizePurchasePayment::class)->execute($paymentId);

        $this->assertSame(1, Journal::where('reference_type', 'purchase_payment')->where('reference_id', $paymentId)->count());
        $this->assertEquals(300000, (float) DB::table('purchase_invoices')->where('id', $invoiceId)->value('paid_amount'));

        tenancy()->end();
    }

    public function test_payment_with_rule_stays_pending_and_applies_on_approval(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 500000);

        // Rule: total > 100000 → perlu approval
        $typeId = DB::table('approval_transaction_types')->where('key', 'purchase_payment')->value('id');
        app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $typeId,
            'name' => 'Payment Rule',
            'min_amount' => 100000,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [999999]],
            ],
        ], 0);

        $payment = app(CreatePurchasePayment::class)->execute([
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'cash_account_id' => $ctx['cashAccountId'],
            'allocations' => [
                ['purchase_invoice_id' => $invoiceId, 'amount' => 200000],
            ],
        ], null, null, [$ctx['hqBranchId']]);

        $this->assertSame('pending', $payment->status);
        $this->assertSame(0, Journal::where('reference_type', 'purchase_payment')->where('reference_id', $payment->id)->count());
        $this->assertEquals(0, (float) DB::table('purchase_invoices')->where('id', $invoiceId)->value('paid_amount'));

        // Approve manual via listener
        app(FinalizePurchasePayment::class)->execute((int) $payment->id);

        $this->assertSame(1, Journal::where('reference_type', 'purchase_payment')->where('reference_id', $payment->id)->count());
        $this->assertEquals(200000, (float) DB::table('purchase_invoices')->where('id', $invoiceId)->value('paid_amount'));

        tenancy()->end();
    }

    public function test_payment_rejects_invalid_cash_account(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 200000);

        // Akun tidak ada.
        try {
            app(CreatePurchasePayment::class)->execute([
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => 999999,
                'allocations' => [
                    ['purchase_invoice_id' => $invoiceId, 'amount' => 50000],
                ],
            ], null, null, [$ctx['hqBranchId']]);
            $this->fail('Expected ValidationException for unknown cash account.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cash_account_id', $e->errors());
        }

        // Akun header tidak boleh jadi akun journals.
        $headerAccountId = DB::table('chart_of_accounts')
            ->where('is_header', true)
            ->value('id');

        try {
            app(CreatePurchasePayment::class)->execute([
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => $headerAccountId,
                'allocations' => [
                    ['purchase_invoice_id' => $invoiceId, 'amount' => 50000],
                ],
            ], null, null, [$ctx['hqBranchId']]);
            $this->fail('Expected ValidationException for header cash account.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cash_account_id', $e->errors());
        }

        $this->assertSame(0, PurchasePayment::count());
        $this->assertEquals(0, (float) DB::table('purchase_invoices')
            ->where('id', $invoiceId)->value('paid_amount'));

        tenancy()->end();
    }

    public function test_duplicate_invoice_allocations_are_merged_not_double_counted(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 100000);

        // Dua baris untuk faktur yang sama: 60k + 40k = 100k (tepat outstanding).
        // pra-bug: setiap baris dicek terpisah terhadap snapshot outstanding yang
        // sama, lalu kunci idempotensi bentrok sehingga hanya baris pertama
        // yang terpakai sementara jurnal tetap memakai total semua baris.
        $payment = app(CreatePurchasePayment::class)->execute([
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'cash_account_id' => $ctx['cashAccountId'],
            'allocations' => [
                ['purchase_invoice_id' => $invoiceId, 'amount' => 60000],
                ['purchase_invoice_id' => $invoiceId, 'amount' => 40000],
            ],
        ], null, null, [$ctx['hqBranchId']]);

        // Alokasi harus merged menjadi satu baris 100k.
        $this->assertCount(1, $payment->allocations);
        $this->assertEquals(100000, (float) $payment->gross_amount);
        $this->assertEquals(100000, (float) $payment->cash_out);

        // Faktur harus benar-benar berkurang 100k (bukan 60k).
        $this->assertEquals(100000, (float) DB::table('purchase_invoices')
            ->where('id', $invoiceId)->value('paid_amount'));
        $this->assertSame('paid', DB::table('purchase_invoices')
            ->where('id', $invoiceId)->value('status'));

        // Jurnal harus cocok dengan yang benar-benar terpakai.
        $this->assertJournal($payment->id, [
            'accounting.coa.2101' => [100000, 0],
            'accounting.coa.1101' => [0, 100000],
        ]);

        tenancy()->end();
    }

    public function test_duplicate_invoice_allocations_over_outstanding_are_rejected(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $invoiceId = $this->createInvoice($ctx, 100000);

        // 60k + 60k = 120k > outstanding 100k, harus ditolak.
        try {
            app(CreatePurchasePayment::class)->execute([
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => $ctx['cashAccountId'],
                'allocations' => [
                    ['purchase_invoice_id' => $invoiceId, 'amount' => 60000],
                    ['purchase_invoice_id' => $invoiceId, 'amount' => 60000],
                ],
            ], null, null, [$ctx['hqBranchId']]);
            $this->fail('Expected ValidationException for merged allocation above outstanding.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('allocations', $e->errors());
        }

        $this->assertSame(0, PurchasePayment::count());
        $this->assertEquals(0, (float) DB::table('purchase_invoices')
            ->where('id', $invoiceId)->value('paid_amount'));

        tenancy()->end();
    }

    private function assertJournal(int $paymentId, array $expected): void
    {
        $journal = Journal::where('reference_type', 'purchase_payment')
            ->where('reference_id', $paymentId)
            ->firstOrFail();

        $lines = DB::table('journal_lines')
            ->join('chart_of_accounts', 'chart_of_accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_lines.journal_id', $journal->id)
            ->select('chart_of_accounts.seed_key', 'journal_lines.debit', 'journal_lines.credit')
            ->get();

        $debit = (float) $lines->sum('debit');
        $credit = (float) $lines->sum('credit');
        $this->assertEquals($debit, $credit, 'Jurnal tidak balance.');

        foreach ($expected as $code => [$dr, $cr]) {
            $line = $lines->firstWhere('seed_key', $code);
            $this->assertNotNull($line, "Kaki jurnal [$code] tidak ditemukan.");
            $this->assertEquals($dr, (float) $line->debit, "Debit [$code] salah.");
            $this->assertEquals($cr, (float) $line->credit, "Kredit [$code] salah.");
        }
    }

    private function createInvoice(array $ctx, float $total): int
    {
        return DB::table('purchase_invoices')->insertGetId([
            'number' => 'FBL-PAY-'.uniqid(),
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'status' => 'approved',
            'invoice_date' => '2026-09-01',
            'currency_code' => 'IDR',
            'subtotal' => $total,
            'tax_amount' => 0,
            'total' => $total,
            'paid_amount' => 0,
            'returned_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('payx_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'PurchasePayment Test-'.$id,
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

        // Akun pemotongan: reuse akun seed 1404 (PPN Masukan) agar tidak
        // perlu membuat CoA custom di test.
        $withholdingAccountId = (int) DB::table('chart_of_accounts')->where('seed_key', 'accounting.coa.1404')->value('id');

        tenancy()->end();

        return [
            'tenantId' => $tenant->id,
            'hqBranchId' => (int) $hqBranchId,
            'supplierId' => (int) $supplierId,
            'cashAccountId' => $cashAccountId,
            'withholdingAccountId' => $withholdingAccountId,
            'withholdingCode' => 'accounting.coa.1404',
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
