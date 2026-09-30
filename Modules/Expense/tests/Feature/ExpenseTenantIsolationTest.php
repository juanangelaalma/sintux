<?php

namespace Modules\Expense\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Models\Journal;
use Modules\Expense\Application\Expense\GetExpenseDetail;
use Modules\Expense\Application\Expense\GetExpenses;
use Modules\Expense\Models\Expense;
use Modules\Expense\Tests\Support\ExpenseFixture;

/**
 * Isolasi tenant dan cabang: user company A tidak pernah melihat atau
 * mengubah data company B (PRD §11).
 *
 * Unlike the other tests, this one needs a SECOND tenant. Both tenants are
 * built once per test process and reset per test, so `tenants:migrate` runs
 * twice instead of once per test.
 */
class ExpenseTenantIsolationTest extends ExpenseDatabaseTestCase
{
    /** @var array{tenant: Tenant, tenantId: string, branchId: int}|null */
    private static ?array $tenantB = null;

    /** @var list<string> */
    private array $extraSchemaNames = [];

    protected function tearDown(): void
    {
        foreach ($this->extraSchemaNames as $schemaName) {
            ExpenseFixture::dropSchema($schemaName);
        }

        $this->extraSchemaNames = [];

        // Schema tenant kedua dibuang setiap test, jadi cache-nya harus
        // ikut dibuang supaya test berikutnya membangunnya ulang.
        self::$tenantB = null;

        parent::tearDown();
    }

    /**
     * Biaya company A tidak terlihat dari daftar company B meski branch ID
     * Called sama.
     */
    public function test_expenses_are_invisible_across_tenants(): void
    {
        $expenseId = (int) $this->createExpense()->id;

        $tenantB = $this->secondTenant();

        $this->assertSame(0, Expense::query()->count(), 'Company B tidak boleh melihat biaya company A.');

        $rows = app(GetExpenses::class)->execute([(int) $tenantB['branchId']], [], 50);
        $this->assertSame(0, $rows->total());

        $this->expectException(ModelNotFoundException::class);
        app(GetExpenseDetail::class)->execute($expenseId, [(int) $tenantB['branchId']]);
    }

    /**
     * Jurnal biaya company A tidak bocor ke company B meski ID-nya sama.
     */
    public function test_journals_are_invisible_across_tenants(): void
    {
        $this->createExpense();

        $this->secondTenant();

        $this->assertSame(0, Journal::query()->count());
    }

    /**
     * Company B punya biaya sendiri dan tidak tercampur dengan company A.
     */
    public function test_each_tenant_sees_only_its_own_expenses(): void
    {
        $this->createExpense();
        $this->createExpense();

        $branchIds = [(int) $this->ctx['branchId']];
        $this->assertSame(2, Expense::query()->count());

        $tenantB = $this->secondTenant();
        $this->createExpenseForBranch((int) $tenantB['branchId'], ['contact_id' => null]);

        tenancy()->end();
        tenancy()->initialize($tenantB['tenant']);

        $this->assertSame(1, Expense::query()->count());

        tenancy()->end();
        tenancy()->initialize($this->tenantA());

        $this->assertSame(2, Expense::query()->count());
    }

    /**
     * Penomoran unik per tenant: dua company boleh sama-sama mulai dari
     * 10001 karena tabelnya terpisah.
     */
    public function test_auto_number_restarts_per_tenant(): void
    {
        $this->createExpense();
        $this->assertSame('10001', Expense::query()->value('number'));

        $tenantB = $this->secondTenant();
        $this->createExpenseForBranch((int) $tenantB['branchId'], ['contact_id' => null]);

        $this->assertSame('10001', Expense::query()->value('number'));
    }

    /**
     * Biaya cabang lain dalam tenant yang sama tidak terlihat saat branch
     * scope-nya dipersempit.
     */
    public function test_expense_outside_branch_scope_is_not_found(): void
    {
        $expenseId = (int) $this->createExpense()->id;

        $otherBranchId = (int) DB::table('branches')->insertGetId([
            'name' => 'Branch Lain',
            'code' => 'B2',
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(0, app(GetExpenses::class)->execute([$otherBranchId], [], 50)->total());

        $this->expectException(ModelNotFoundException::class);
        app(GetExpenseDetail::class)->execute($expenseId, [$otherBranchId]);
    }

    private function tenantA(): Tenant
    {
        return Tenant::query()->findOrFail(ExpenseFixture::tenantId());
    }

    /**
     * Tenant kedua, dibuat sekali per proses lalu di-reset per test.
     *
     * @return array{tenant: Tenant, tenantId: string, branchId: int}
     */
    private function secondTenant(): array
    {
        tenancy()->end();

        if (self::$tenantB !== null) {
            tenancy()->initialize(self::$tenantB['tenant']);

            return self::$tenantB;
        }

        $id = 'expenses_isolation_b';
        $schemaName = 'sch_'.$id;
        $this->extraSchemaNames[] = $schemaName;

        ExpenseFixture::dropSchema($schemaName);
        DB::table('tenants')->where('id', $id)->delete();

        $tenant = Tenant::query()->create([
            'id' => $id,
            'name' => 'Expense Isolation B',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        // Migrasi eksplisit supaya tenant kedua dijamin punya tabel yang sama
        // dengan tenant pertama, apa pun yang terjadi di pipeline pembuatan tenant.
        Artisan::call('tenants:migrate', ['--tenants' => [$id]]);

        tenancy()->initialize($tenant);

        $branchId = (int) DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return self::$tenantB = [
            'tenant' => $tenant,
            'tenantId' => $id,
            'branchId' => $branchId,
        ];
    }
}
