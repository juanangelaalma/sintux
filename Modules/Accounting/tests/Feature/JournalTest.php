<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Accounting\Application\Journal\DefaultPayableAccount;
use Modules\Accounting\Application\Journal\RecordJournal;
use Modules\Accounting\Application\Journal\ResolvePayableAccount;
use Modules\Accounting\Application\Journal\ReverseJournal;
use Modules\Accounting\Models\ChartOfAccount;
use Modules\Accounting\Models\Journal;
use Tests\TestCase;

class JournalTest extends TestCase
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

    public function test_record_balanced_journal(): void
    {
        [$tenantId, $branchId, $debitId, $creditId] = $this->seedTenantWithAccounts();

        tenancy()->initialize($tenantId);

        $journal = app(RecordJournal::class)->execute([
            'branch_id' => $branchId,
            'journal_date' => '2026-09-23',
            'reference_type' => 'purchase_return',
            'reference_id' => 1,
            'memo' => 'Retur test',
            'lines' => [
                ['account_id' => $debitId, 'debit' => 112000, 'credit' => 0],
                ['account_id' => $creditId, 'debit' => 0, 'credit' => 112000],
            ],
        ]);

        $this->assertTrue($journal->exists);
        $this->assertSame('posted', $journal->status);
        $this->assertSame(2, $journal->lines()->count());
        $this->assertEquals(112000, (float) $journal->lines()->sum('debit'));
        $this->assertEquals(112000, (float) $journal->lines()->sum('credit'));

        tenancy()->end();
    }

    public function test_unbalanced_journal_is_rejected_without_rows(): void
    {
        [$tenantId, $branchId, $debitId, $creditId] = $this->seedTenantWithAccounts();

        tenancy()->initialize($tenantId);

        try {
            app(RecordJournal::class)->execute([
                'branch_id' => $branchId,
                'journal_date' => '2026-09-23',
                'lines' => [
                    ['account_id' => $debitId, 'debit' => 100000, 'credit' => 0],
                    ['account_id' => $creditId, 'debit' => 0, 'credit' => 99000],
                ],
            ]);
            $this->fail('Expected ValidationException for unbalanced journal.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('lines', $e->errors());
        }

        $this->assertSame(0, Journal::query()->count());

        tenancy()->end();
    }

    public function test_header_account_is_rejected(): void
    {
        [$tenantId, $branchId, $debitId] = $this->seedTenantWithAccounts();

        tenancy()->initialize($tenantId);

        $headerId = ChartOfAccount::query()->where('is_header', true)->value('id');

        try {
            app(RecordJournal::class)->execute([
                'branch_id' => $branchId,
                'journal_date' => '2026-09-23',
                'lines' => [
                    ['account_id' => $headerId, 'debit' => 50000, 'credit' => 0],
                    ['account_id' => $debitId, 'debit' => 0, 'credit' => 50000],
                ],
            ]);
            $this->fail('Expected ValidationException for header account.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $this->assertSame(0, Journal::query()->count());

        tenancy()->end();
    }

    public function test_reverse_creates_swapped_journal(): void
    {
        [$tenantId, $branchId, $debitId, $creditId] = $this->seedTenantWithAccounts();

        tenancy()->initialize($tenantId);

        $original = app(RecordJournal::class)->execute([
            'branch_id' => $branchId,
            'journal_date' => '2026-09-23',
            'lines' => [
                ['account_id' => $debitId, 'debit' => 75000, 'credit' => 0],
                ['account_id' => $creditId, 'debit' => 0, 'credit' => 75000],
            ],
        ]);

        $reversal = app(ReverseJournal::class)->execute($original->id);

        $this->assertSame($original->id, (int) $reversal->reversal_of_id);
        $lines = $reversal->lines()->orderBy('id')->get();
        $this->assertCount(2, $lines);
        $this->assertEquals(75000, (float) $lines->where('account_id', $debitId)->first()->credit);
        $this->assertEquals(75000, (float) $lines->where('account_id', $creditId)->first()->debit);

        tenancy()->end();
    }

    public function test_payable_account_resolves_to_default(): void
    {
        [$tenantId] = $this->seedTenantWithAccounts();

        tenancy()->initialize($tenantId);

        $default = app(DefaultPayableAccount::class)->execute();
        $this->assertSame('accounting.coa.2101', (string) $default->seed_key);

        $resolved = app(ResolvePayableAccount::class)->execute(null);
        $this->assertSame($default->id, $resolved->id);

        tenancy()->end();
    }

    public function test_journals_are_isolated_per_tenant(): void
    {
        [$tenantA, $branchA, $debitA, $creditA] = $this->seedTenantWithAccounts();
        [$tenantB] = $this->seedTenantWithAccounts();

        tenancy()->initialize($tenantA);
        app(RecordJournal::class)->execute([
            'branch_id' => $branchA,
            'journal_date' => '2026-09-23',
            'lines' => [
                ['account_id' => $debitA, 'debit' => 10000, 'credit' => 0],
                ['account_id' => $creditA, 'debit' => 0, 'credit' => 10000],
            ],
        ]);
        tenancy()->end();

        tenancy()->initialize($tenantB);
        $this->assertSame(0, Journal::query()->count());
        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: int}
     */
    private function seedTenantWithAccounts(): array
    {
        $id = uniqid('jnl_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Journal Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $branchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $debitId = ChartOfAccount::query()->where('is_header', false)->orderBy('id')->value('id');
        $creditId = ChartOfAccount::query()->where('is_header', false)->orderByDesc('id')->value('id');

        tenancy()->end();

        return [$tenant->id, (int) $branchId, (int) $debitId, (int) $creditId];
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
