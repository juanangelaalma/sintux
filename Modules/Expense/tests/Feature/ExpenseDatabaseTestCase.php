<?php

namespace Modules\Expense\Tests\Feature;

use Modules\Expense\Application\Expense\CreateExpense;
use Modules\Expense\Models\Expense;
use Modules\Expense\Tests\Support\ExpenseFixture;
use Tests\TestCase;

/**
 * Base test Expense yang butuh database tenant.
 *
 * Memakai satu tenant bersama untuk seluruh proses test (lihat
 * ExpenseFixture) supaya `tenants:migrate` tidak dijalankan 51 kali, yang
 * memperpanjang lock window di database `testing`.
 */
abstract class ExpenseDatabaseTestCase extends TestCase
{
    /** @var array<string, mixed> */
    protected array $ctx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = ExpenseFixture::prepare();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createExpense(array $overrides = []): Expense
    {
        return $this->createExpenseForBranch((int) $this->ctx['branchId'], $overrides);
    }

    /**
     * Buat biaya pada cabang tertentu, untuk test yang butuh cabang kedua.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function createExpenseForBranch(int $branchId, array $overrides = []): Expense
    {
        return app(CreateExpense::class)->execute(
            ExpenseFixture::payload($this->ctx, $overrides),
            $branchId,
            [$branchId],
        );
    }
}
