<?php

namespace Modules\Payment\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Tests\TestCase;

class PurchasePaymentHttpTest extends TestCase
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

    public function test_new_prefills_invoice_and_supplier(): void
    {
        $ctx = $this->seedContext();
        $invoiceId = $this->createInvoice($ctx, 750000);

        $response = $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->get(route('purchase-payments.new', ['createdFrom' => $invoiceId]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Payment/Purchase/create')
            ->where('mode', 'invoice')
            ->where('supplierId', $ctx['supplierId'])
            ->has('paymentMethods')
            ->has('accounts')
            ->has('payableInvoices', 1)
            ->where('prefillAllocation.purchase_invoice_id', $invoiceId)
            ->where('prefillAllocation.amount', 750000)
        );
    }

    public function test_new_deposit_mode_has_no_allocations(): void
    {
        $ctx = $this->seedContext();

        $response = $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->get(route('purchase-payments.new', [
                'mode' => 'deposit',
                'supplier_id' => $ctx['supplierId'],
            ]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('mode', 'deposit')
            ->where('supplierId', $ctx['supplierId'])
        );
    }

    public function test_store_creates_payment_and_redirects_to_show(): void
    {
        $ctx = $this->seedContext();
        $invoiceId = $this->createInvoice($ctx, 500000);

        $response = $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->post(route('purchase-payments.store'), [
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => $ctx['cashAccountId'],
                'mode' => 'invoice',
                'payment_date' => '2026-09-24',
                'allocations' => [
                    ['purchase_invoice_id' => $invoiceId, 'amount' => 200000],
                ],
            ]);

        tenancy()->initialize($ctx['tenantId']);
        $paymentId = (int) DB::table('purchase_payments')->orderByDesc('id')->value('id');
        tenancy()->end();

        $response->assertRedirect(route('purchase-payments.show', $paymentId));
    }

    public function test_store_rejects_overpay(): void
    {
        $ctx = $this->seedContext();
        $invoiceId = $this->createInvoice($ctx, 100000);

        $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->post(route('purchase-payments.store'), [
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => $ctx['cashAccountId'],
                'mode' => 'invoice',
                'payment_date' => '2026-09-24',
                'allocations' => [
                    ['purchase_invoice_id' => $invoiceId, 'amount' => 999999],
                ],
            ])
            ->assertSessionHasErrors('allocations');
    }

    public function test_store_deposit_mode_records_deposit(): void
    {
        $ctx = $this->seedContext();

        $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->post(route('purchase-payments.store'), [
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => $ctx['cashAccountId'],
                'mode' => 'deposit',
                'amount' => 400000,
                'payment_date' => '2026-09-24',
            ])
            ->assertRedirect();

        tenancy()->initialize($ctx['tenantId']);
        $payment = DB::table('purchase_payments')->orderByDesc('id')->first();
        $this->assertSame('deposit', $payment->mode);
        $this->assertEquals(400000, (float) $payment->deposit_remaining);
        tenancy()->end();
    }

    public function test_show_renders_payment_detail(): void
    {
        $ctx = $this->seedContext();
        $invoiceId = $this->createInvoice($ctx, 500000);

        $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->post(route('purchase-payments.store'), [
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => $ctx['cashAccountId'],
                'mode' => 'invoice',
                'payment_date' => '2026-09-24',
                'allocations' => [
                    ['purchase_invoice_id' => $invoiceId, 'amount' => 100000],
                ],
            ])
            ->assertRedirect();

        tenancy()->initialize($ctx['tenantId']);
        $paymentId = (int) DB::table('purchase_payments')->orderByDesc('id')->value('id');
        tenancy()->end();

        $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->get(route('purchase-payments.show', $paymentId))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Payment/Purchase/show')
                ->has('payment.payment')
                ->has('payment.allocations', 1)
            );
    }

    public function test_show_rejects_payment_outside_branch_scope(): void
    {
        $ctx = $this->seedContext();
        $invoiceId = $this->createInvoice($ctx, 500000);

        $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->post(route('purchase-payments.store'), [
                'branch_id' => $ctx['hqBranchId'],
                'supplier_id' => $ctx['supplierId'],
                'cash_account_id' => $ctx['cashAccountId'],
                'mode' => 'invoice',
                'payment_date' => '2026-09-24',
                'allocations' => [
                    ['purchase_invoice_id' => $invoiceId, 'amount' => 100000],
                ],
            ])
            ->assertRedirect();

        tenancy()->initialize($ctx['tenantId']);
        $paymentId = (int) DB::table('purchase_payments')->orderByDesc('id')->value('id');
        tenancy()->end();

        // User branch lain (bukan HQ) tidak boleh melihat payment HQ.
        $otherUser = $this->createBranchScopedUser($ctx, 'Branch B');

        $this->withSession([
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $otherUser['branchId'],
        ])->actingAs($otherUser['user'])
            ->get(route('purchase-payments.show', $paymentId))
            ->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{user: User, branchId: int}
     */
    private function createBranchScopedUser(array $ctx, string $branchLabel): array
    {
        tenancy()->initialize($ctx['tenantId']);

        $branchId = DB::table('branches')->insertGetId([
            'name' => $branchLabel,
            'code' => 'B'.uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'other_'.uniqid().'@acme.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $ctx['tenantId'],
            'branch_id' => $branchId,
            'role' => 'member',
            'is_default' => true,
        ]);

        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $branchId,
        ]);

        return ['user' => $user, 'branchId' => (int) $branchId];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function tenantSession(array $ctx): array
    {
        return [
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $ctx['hqBranchId'],
        ];
    }

    private function createInvoice(array $ctx, float $total): int
    {
        tenancy()->initialize($ctx['tenantId']);

        $id = DB::table('purchase_invoices')->insertGetId([
            'number' => 'FBL-HTTP-'.uniqid(),
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $ctx['supplierId'],
            'status' => PurchaseInvoiceStatus::Approved->value,
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

        tenancy()->end();

        return $id;
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('payh_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Payment HTTP Test-'.$id,
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

        $user = User::factory()->create([
            'email' => 'payh_'.$id.'@acme.test',
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

        return [
            'tenantId' => $tenant->id,
            'hqBranchId' => (int) $hqBranchId,
            'supplierId' => (int) $supplierId,
            'cashAccountId' => $cashAccountId,
            'user' => $user,
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
