<?php

namespace Modules\Contact\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Application\CreateCompanyUser;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Tests\Support\CompanyTestFixture;
use Tests\TestCase;

class ContactValidationAndAtomicityTest extends TestCase
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
        $this->seed(RolePermissionSeeder::class);
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

    public function test_notes_exceeding_5000_characters_is_rejected(): void
    {
        [$tenantId, $branchId, $admin] = $this->createCompanyUser('admin');
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $longNotes = str_repeat('a', 5001);

        $this->actingAs($admin)
            ->post(route('company.contacts.store', 'customers'), [
                'branch_id' => $branchId,
                'name' => 'Long Notes Customer',
                'registered_at' => '2026-08-01',
                'notes' => $longNotes,
            ])
            ->assertSessionHasErrors(['notes']);
    }

    public function test_notes_up_to_5000_characters_is_accepted(): void
    {
        [$tenantId, $branchId, $admin] = $this->createCompanyUser('admin');
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $validNotes = str_repeat('a', 5000);

        $this->actingAs($admin)
            ->post(route('company.contacts.store', 'customers'), [
                'branch_id' => $branchId,
                'name' => 'Valid Notes Customer',
                'registered_at' => '2026-08-01',
                'notes' => $validNotes,
            ])
            ->assertRedirect(route('company.contacts.index', 'customers'));

        tenancy()->initialize($tenantId);
        $this->assertDatabaseHas('contacts', [
            'name' => 'Valid Notes Customer',
            'notes' => $validNotes,
        ]);
    }

    public function test_store_contact_enforces_context_branch_scope(): void
    {
        [$tenant, $hqId, $branchA, $branchB] = $this->createTenantWithBranches();

        // User multi branch (accessible: branchA and branchB)
        $user = app(CreateCompanyUser::class)->execute((string) $tenant->id, [
            'name' => 'Context Branch User',
            'email' => 'context@test.com',
            'password' => 'password',
            'company_role' => 'admin',
            'branch_id' => $branchA,
            'scope' => 'branch',
            'allowed_branch_ids' => [$branchA, $branchB],
        ]);

        // Active session scope set to branchA ONLY
        session([
            'active_tenant_id' => $tenant->id,
            'active_branch_id' => $branchA,
            'branch_scope' => 'branch',
        ]);

        // Trying to post to branchB while session scope is branchA -> rejected by validation
        $this->actingAs($user)
            ->post(route('company.contacts.store', 'customers'), [
                'branch_id' => $branchB,
                'name' => 'Scope Violation Customer',
                'registered_at' => '2026-08-01',
            ])
            ->assertSessionHasErrors(['branch_id']);
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyUser(string $companyRole): array
    {
        $id = uniqid('val_');
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Validation Test Corp',
            'schema_name' => 'sch_'.$id,
            'is_active' => true,
        ]);

        $this->activeSchemaName = 'sch_'.$id;

        $branchId = CompanyTestFixture::branch($tenant, [
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
        ]);

        $user = app(CreateCompanyUser::class)->execute((string) $tenant->id, [
            'name' => 'Test User '.$companyRole,
            'email' => $companyRole.'_'.$id.'@test.com',
            'password' => 'password',
            'company_role' => $companyRole,
            'branch_id' => $branchId,
            'scope' => 'branch',
        ]);

        return [(string) $tenant->id, $branchId, $user];
    }

    /**
     * @return array{0: Tenant, 1: int, 2: int, 3: int}
     */
    private function createTenantWithBranches(): array
    {
        $id = uniqid('br_');
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Multi Branch Corp',
            'schema_name' => 'sch_'.$id,
            'is_active' => true,
        ]);

        $this->activeSchemaName = 'sch_'.$id;

        $hqId = CompanyTestFixture::branch($tenant, [
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
        ]);
        $branchA = CompanyTestFixture::branch($tenant, [
            'name' => 'Branch A',
            'code' => 'A',
        ]);
        $branchB = CompanyTestFixture::branch($tenant, [
            'name' => 'Branch B',
            'code' => 'B',
        ]);

        return [$tenant, $hqId, $branchA, $branchB];
    }

    private function cleanupCentralTables(): void
    {
        $this->deleteIfTableExists('company_user_branches');
        $this->deleteIfTableExists('company_users');
        $this->deleteIfTableExists('tenants');
        $this->deleteIfTableExists('users');
    }

    private function deleteIfTableExists(string $table): void
    {
        $exists = DB::selectOne(
            'SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = current_schema()
                AND table_name = ?
            ) AS exists',
            [$table]
        );

        if ($exists && (bool) $exists->exists) {
            DB::table($table)->delete();
        }
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            $safeSchemaName = str_replace('"', '""', $schemaName);
            DB::statement('DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE');
        } catch (\Throwable $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name
                 FROM information_schema.schemata
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
