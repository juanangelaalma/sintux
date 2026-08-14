<?php

namespace Modules\Contact\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Application\CreateCompanyUser;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Tests\Support\CompanyTestFixture;
use Tests\TestCase;

class ContactBranchScopeTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        /*
         * Remove tenant schemas left behind by previous
         * failed/interrupted tests.
         */
        $this->dropLeftoverSchemas();

        /*
         * Clean central tables in FK-safe order.
         */
        $this->cleanupCentralTables();

        $this->seed(RolePermissionSeeder::class);
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        /*
         * Drop the tenant schema created by this test.
         */
        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
            $this->activeSchemaName = null;
        }

        parent::tearDown();
    }

    public function test_multi_branch_member_sees_combined_contacts_in_all_scope(): void
    {
        [$tenant, $hqId, $branchA, $branchB] = $this->createTenantWithBranches();
        $user = $this->createMember($tenant, 'multi@acme.test', $hqId, [$branchA, $branchB]);

        session(['active_tenant_id' => $tenant->id, 'active_branch_id' => $hqId]);

        $this->actingAs($user)->post(route('company.contacts.store', 'customers'), [
            'branch_id' => $branchA,
            'name' => 'Alpha Customer',
            'registered_at' => '2026-08-01',
        ])->assertRedirect(route('company.contacts.index', 'customers'));

        $this->actingAs($user)->post(route('company.contacts.store', 'customers'), [
            'branch_id' => $branchB,
            'name' => 'Beta Customer',
            'registered_at' => '2026-08-01',
        ])->assertRedirect(route('company.contacts.index', 'customers'));

        $response = $this->actingAs($user)->get(route('company.contacts.index', 'customers'));
        $response->assertStatus(200);
        $response->assertSee('Alpha Customer');
        $response->assertSee('Beta Customer');
    }

    public function test_single_branch_member_only_sees_own_branch_contacts(): void
    {
        [$tenant, $hqId, $branchA, $branchB] = $this->createTenantWithBranches();
        $user = $this->createMember($tenant, 'solo@acme.test', $branchB);

        session(['active_tenant_id' => $tenant->id, 'branch_scope' => 'branch', 'active_branch_id' => $branchB]);

        tenancy()->initialize($tenant);
        DB::table('contacts')->insert([
            ['branch_id' => $branchA, 'type' => 'customer', 'name' => 'Foreign Customer', 'registered_at' => '2026-08-01', 'is_active' => true],
            ['branch_id' => $branchB, 'type' => 'customer', 'name' => 'Own Customer', 'registered_at' => '2026-08-01', 'is_active' => true],
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->get(route('company.contacts.index', 'customers'));
        $response->assertStatus(200);
        $response->assertSee('Own Customer');
        $response->assertDontSee('Foreign Customer');
    }

    public function test_single_branch_member_cannot_read_other_branch_contact(): void
    {
        [$tenant, $hqId, $branchA, $branchB] = $this->createTenantWithBranches();
        $user = $this->createMember($tenant, 'solo2@acme.test', $branchB);

        session(['active_tenant_id' => $tenant->id, 'branch_scope' => 'branch', 'active_branch_id' => $branchB]);

        tenancy()->initialize($tenant);
        $foreignId = DB::table('contacts')->insertGetId([
            'branch_id' => $branchA,
            'type' => 'customer',
            'name' => 'Foreign Customer',
            'registered_at' => '2026-08-01',
            'is_active' => true,
        ]);
        tenancy()->end();

        $this->actingAs($user)
            ->get(route('company.contacts.edit', ['type' => 'customers', 'id' => $foreignId]))
            ->assertStatus(404);

        $this->actingAs($user)
            ->put(route('company.contacts.update', ['type' => 'customers', 'id' => $foreignId]), ['name' => 'Nope'])
            ->assertStatus(404);

        $this->actingAs($user)
            ->delete(route('company.contacts.destroy', ['type' => 'customers', 'id' => $foreignId]))
            ->assertStatus(404);
    }

    public function test_store_contact_with_inaccessible_branch_is_rejected(): void
    {
        [$tenant, $hqId, $branchA, $branchB] = $this->createTenantWithBranches();
        $user = $this->createMember($tenant, 'solo3@acme.test', $branchB);

        session(['active_tenant_id' => $tenant->id, 'branch_scope' => 'branch', 'active_branch_id' => $branchB]);

        $this->actingAs($user)->post(route('company.contacts.store', 'customers'), [
            'branch_id' => $branchA,
            'name' => 'Sneaky Customer',
            'registered_at' => '2026-08-01',
        ])->assertSessionHasErrors('branch_id');

        tenancy()->initialize($tenant);
        $this->assertDatabaseMissing('contacts', ['name' => 'Sneaky Customer']);
        tenancy()->end();
    }

    public function test_switch_to_inaccessible_branch_is_rejected(): void
    {
        [$tenant, $hqId, $branchA, $branchB] = $this->createTenantWithBranches();
        $user = $this->createMember($tenant, 'solo4@acme.test', $branchB);

        session(['active_tenant_id' => $tenant->id, 'branch_scope' => 'branch', 'active_branch_id' => $branchB]);

        $this->actingAs($user)->post(route('company.branches.switch'), [
            'branch_id' => $branchA,
        ])->assertStatus(403);

        $this->assertSame('branch', session('branch_scope'));
        $this->assertSame($branchB, session('active_branch_id'));
    }

    public function test_switch_to_accessible_branch_succeeds(): void
    {
        [$tenant, $hqId, $branchA, $branchB] = $this->createTenantWithBranches();
        $user = $this->createMember($tenant, 'multi2@acme.test', $branchA, [$branchB]);

        session(['active_tenant_id' => $tenant->id, 'branch_scope' => 'branch', 'active_branch_id' => $branchA]);

        $this->actingAs($user)->post(route('company.branches.switch'), [
            'branch_id' => $branchB,
        ])->assertRedirect();

        $this->assertSame('branch', session('branch_scope'));
        $this->assertSame($branchB, session('active_branch_id'));
    }

    public function test_switching_to_hq_sets_all_scope(): void
    {
        [$tenant, $hqId, $branchA, $branchB] = $this->createTenantWithBranches();
        $user = $this->createMember($tenant, 'hq@acme.test', $hqId);

        session(['active_tenant_id' => $tenant->id, 'branch_scope' => 'branch', 'active_branch_id' => $branchA]);

        $this->actingAs($user)->post(route('company.branches.switch'), [
            'branch_id' => $hqId,
        ])->assertRedirect();

        $this->assertSame('all', session('branch_scope'));
        $this->assertSame($hqId, session('active_branch_id'));
    }

    /**
     * @return array{0: Tenant, 1: int, 2: int, 3: int}
     */
    private function createTenantWithBranches(): array
    {
        $id = uniqid('scope_');
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Scope Test Corp',
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

    /**
     * @param  list<int>  $extraAllowed
     */
    private function createMember(Tenant $tenant, string $email, int $homeBranch, array $extraAllowed = []): User
    {
        return app(CreateCompanyUser::class)->execute((string) $tenant->id, [
            'name' => 'Contact Scope Admin',
            'email' => $email,
            'password' => 'password',
            'company_role' => 'admin',
            'branch_id' => $homeBranch,
            'scope' => 'branch',
            'allowed_branch_ids' => $extraAllowed,
        ]);
    }

    /**
     * Clean central/public tables while respecting foreign key dependencies.
     */
    private function cleanupCentralTables(): void
    {
        $this->deleteIfTableExists('company_user_branches');
        $this->deleteIfTableExists('company_users');

        $this->deleteIfTableExists('tenants');

        $this->deleteIfTableExists('users');
    }

    /**
     * Delete all rows only if the table exists in the current PostgreSQL schema.
     */
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

    /**
     * Drop a tenant schema safely.
     */
    private function dropSchema(string $schemaName): void
    {
        try {
            $safeSchemaName = str_replace('"', '""', $schemaName);

            DB::statement(
                'DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE'
            );
        } catch (\Throwable $e) {
            /*
             * Cleanup failure should not hide the actual test failure.
             */
        }
    }

    /**
     * Drop leftover test tenant schemas.
     */
    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name
                 FROM information_schema.schemata
                 WHERE schema_name NOT IN (
                     'public',
                     'information_schema'
                 )
                 AND schema_name NOT LIKE 'pg_%'"
            );

            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;

                /*
                 * Only delete schemas generated by tests.
                 */
                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Throwable $e) {
            /*
             * Ignore cleanup errors.
             */
        }
    }
}
