<?php

namespace Modules\Expense\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\ChartOfAccountQuery;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Models\CompanyUser;
use Modules\Expense\Models\Expense;
use Modules\Expense\Tests\Support\ExpenseFixture;
use Tests\TestCase;

/**
 * Alur HTTP: daftar, form, simpan, detail, tag, dan guard permission.
 */
class ExpenseHttpTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        ExpenseFixture::cleanupCentralTables();

        // Katalog role dan permission adalah tabel central. Seeder harus
        // jalan sebelum tenancy diinisialisasi, kalau tidak query-nya
        // mengarah ke schema tenant dan tabelnya tidak ada di sana.
        $this->seed(RolePermissionSeeder::class);

        $this->ctx = ExpenseFixture::prepare();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    public function test_index_renders_list_page_with_summary_and_filters(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->get(route('expenses.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expense/Expenses/index')
                ->has('expenses.data')
                ->has('summary.this_month.total')
                ->has('summary.this_month.count')
                ->has('summary.last_30_days.total')
                ->has('summary.unpaid.total')
                ->has('statusCounts.open')
                ->has('statusCounts.closed')
                ->has('filters'));
    }

    public function test_create_renders_form_with_option_sources_for_the_seventeen_columns(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->get(route('expenses.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expense/Expenses/create')
                ->has('cashAccounts')
                ->has('expenseAccounts')
                ->has('taxes')
                ->has('paymentMethods')
                ->has('contacts')
                ->has('tags'));
    }

    public function test_store_creates_expense_and_redirects_to_detail(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->post(route('expenses.store'), $this->payload())
            ->assertRedirect();

        ExpenseFixture::initialize();

        $expense = Expense::query()->firstOrFail();

        $this->assertSame('closed', $expense->status->value);
        $this->assertSame('10001', $expense->number);
    }

    public function test_store_rejects_line_with_non_expense_account(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->post(route('expenses.store'), $this->payload([
                'lines' => [[
                    'account_id' => ExpenseFixture::firstLeafAccountId('REVENUE'),
                    'description' => 'Pendapatan, bukan biaya',
                    'tax_id' => null,
                    'amount' => 50000,
                ]],
            ]))
            ->assertSessionHasErrors('lines.0.account_id');

        ExpenseFixture::initialize();

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_store_requires_at_least_one_line(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->post(route('expenses.store'), $this->payload(['lines' => []]))
            ->assertSessionHasErrors('lines');
    }

    public function test_store_requires_payment_method(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->post(route('expenses.store'), $this->payload(['payment_method_id' => null]))
            ->assertSessionHasErrors('payment_method_id');
    }

    public function test_store_rejects_duplicate_number_with_indonesian_message(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->post(route('expenses.store'), $this->payload(['number' => 'EXP-DUP']));

        // ResolveTenant mengakhiri tenancy di akhir request.
        ExpenseFixture::initialize();

        $this->actingAs($user)
            ->post(route('expenses.store'), $this->payload(['number' => 'EXP-DUP']))
            ->assertSessionHasErrors([
                'number' => 'Nomor biaya sudah dipakai. Gunakan nomor lain.',
            ]);

        ExpenseFixture::initialize();

        $this->assertSame(1, Expense::query()->count());
    }

    public function test_show_renders_detail_for_expense_in_branch_scope(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)->post(route('expenses.store'), $this->payload());

        ExpenseFixture::initialize();

        $expenseId = (int) Expense::query()->value('id');

        $this->actingAs($user)
            ->get(route('expenses.show', ['expense' => $expenseId]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Expense/Expenses/show')
                ->where('expense.id', $expenseId)
                ->has('expense.lines')
                ->has('expense.tags'));
    }

    public function test_show_returns_404_for_expense_outside_branch_scope(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)->post(route('expenses.store'), $this->payload());

        ExpenseFixture::initialize();

        $expenseId = (int) Expense::query()->value('id');

        $otherBranchId = (int) DB::table('branches')->insertGetId([
            'name' => 'Branch Lain',
            'code' => 'B2',
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // User lain dengan role owner di cabang berbeda. Branch scope-nya
        // tidak mencakup biaya yang dibuat di branch A.
        $otherUser = User::factory()->create([
            'email' => 'other_'.uniqid().'@example.test',
            'role' => 'user',
        ]);

        $otherCompanyUser = CompanyUser::query()->create([
            'user_id' => $otherUser->id,
            'tenant_id' => ExpenseFixture::tenantId(),
            'branch_id' => $otherBranchId,
            'role' => 'owner',
            'is_default' => true,
        ]);

        tenancy()->end();

        DB::table('company_user_branches')->insert([
            'company_user_id' => $otherCompanyUser->id,
            'branch_id' => $otherBranchId,
        ]);

        tenancy()->initialize(Tenant::query()->findOrFail(ExpenseFixture::tenantId()));
        session([
            'active_tenant_id' => ExpenseFixture::tenantId(),
            'active_branch_id' => $otherBranchId,
        ]);

        $this->actingAs($otherUser)
            ->get(route('expenses.show', ['expense' => $expenseId]))
            ->assertNotFound();
    }

    public function test_tag_store_creates_tag_and_is_idempotent(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->post(route('expenses.tags.store'), ['name' => 'Proyek Khusus'])
            ->assertCreated()
            ->assertJsonPath('name', 'Proyek Khusus');

        $this->actingAs($user)
            ->post(route('expenses.tags.store'), ['name' => 'Proyek Khusus'])
            ->assertCreated();

        ExpenseFixture::initialize();

        $this->assertSame(1, DB::table('expense_tags')->where('name', 'Proyek Khusus')->count());
    }

    public function test_tag_store_requires_name(): void
    {
        $user = $this->actingUser();

        $this->actingAs($user)
            ->post(route('expenses.tags.store'), [])
            ->assertSessionHasErrors('name');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('expenses.index'))->assertRedirect(route('login'));
    }

    public function test_user_without_expense_permission_is_forbidden(): void
    {
        $this->actingUser(role: 'none');

        $user = User::factory()->create([
            'email' => 'noperm_'.uniqid().'@example.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::query()->create([
            'user_id' => $user->id,
            'tenant_id' => ExpenseFixture::tenantId(),
            'branch_id' => (int) $this->ctx['branchId'],
            'role' => 'member',
            'is_default' => true,
        ]);

        tenancy()->end();

        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => (int) $this->ctx['branchId'],
        ]);

        tenancy()->initialize(Tenant::query()->findOrFail(ExpenseFixture::tenantId()));
        session([
            'active_tenant_id' => ExpenseFixture::tenantId(),
            'active_branch_id' => (int) $this->ctx['branchId'],
        ]);

        // Role member hanya punya dashboard.view, sehingga guard
        // expense.view harus menahan request di middleware.
        $this->actingAs($user)
            ->get(route('expenses.index'))
            ->assertForbidden();
    }

    /**
     * User dengan membership di cabang aktif. Role companyUser `owner`
     * punya semua permission tanpa bergantung pada baris tabel `roles`.
     */
    private function actingUser(?string $role = 'owner'): User
    {
        $branchId = (int) $this->ctx['branchId'];

        $user = User::factory()->create([
            'email' => 'exp_'.uniqid().'@example.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::query()->create([
            'user_id' => $user->id,
            'tenant_id' => ExpenseFixture::tenantId(),
            'branch_id' => $branchId,
            'role' => $role ?? 'member',
            'is_default' => true,
        ]);

        tenancy()->end();

        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $branchId,
        ]);

        tenancy()->initialize(Tenant::query()->findOrFail(ExpenseFixture::tenantId()));
        session([
            'active_tenant_id' => ExpenseFixture::tenantId(),
            'active_branch_id' => $branchId,
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        $charts = app(ChartOfAccountQuery::class);

        return array_merge([
            'pay_from_account_id' => (int) $charts->listCashAndBank()[0]['id'],
            'is_pay_later' => false,
            'contact_id' => (int) $this->ctx['supplierId'],
            'transaction_date' => now()->toDateString(),
            'payment_method_id' => (int) $this->ctx['paymentMethodId'],
            'number' => null,
            'tag_ids' => [],
            'billing_address' => null,
            'is_tax_inclusive' => false,
            'memo' => null,
            'withholding' => null,
            'lines' => [[
                'account_id' => (int) $charts->listForExpense()[0]['id'],
                'description' => 'Beban operasional',
                'tax_id' => (int) $this->ctx['taxId'],
                'amount' => 100000,
            ]],
        ], $overrides);
    }
}
