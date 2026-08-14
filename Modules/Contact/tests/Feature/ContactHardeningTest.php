<?php

namespace Modules\Contact\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Application\CreateCompanyUser;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Tests\Support\CompanyTestFixture;
use Tests\TestCase;

class ContactHardeningTest extends TestCase
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

    public function test_accessing_out_of_scope_contact_returns_404_anti_enumeration(): void
    {
        [$tenant, $hqId, $branchA, $branchB] = $this->createTenantWithBranches();

        // Admin only scoped to branchA
        $admin = app(CreateCompanyUser::class)->execute((string) $tenant->id, [
            'name' => 'Branch A Admin',
            'email' => 'admin_a@test.com',
            'password' => 'password',
            'company_role' => 'admin',
            'branch_id' => $branchA,
            'scope' => 'branch',
            'allowed_branch_ids' => [$branchA],
        ]);

        // Create contact in branchB
        tenancy()->initialize($tenant->id);
        $contactInBranchBId = DB::table('contacts')->insertGetId([
            'branch_id' => $branchB,
            'type' => 'customer',
            'name' => 'Branch B Secret Customer',
            'registered_at' => '2026-08-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();

        session(['active_tenant_id' => $tenant->id, 'active_branch_id' => $branchA]);

        // Attempt edit out of scope -> 404 (not 403)
        $this->actingAs($admin)
            ->get(route('company.contacts.edit', ['type' => 'customers', 'id' => $contactInBranchBId]))
            ->assertStatus(404);

        // Attempt update out of scope -> 404 (not 403)
        $this->actingAs($admin)
            ->put(route('company.contacts.update', ['type' => 'customers', 'id' => $contactInBranchBId]), [
                'name' => 'Attempted Update',
            ])
            ->assertStatus(404);

        // Attempt delete out of scope -> 404 (not 403)
        $this->actingAs($admin)
            ->delete(route('company.contacts.destroy', ['type' => 'customers', 'id' => $contactInBranchBId]))
            ->assertStatus(404);
    }

    public function test_type_url_mismatch_returns_404(): void
    {
        [$tenantId, $branchId, $admin] = $this->createCompanyUser('admin');
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // Create customer
        $this->actingAs($admin)->post(route('company.contacts.store', 'customers'), [
            'branch_id' => $branchId,
            'name' => 'Actual Customer',
            'registered_at' => '2026-08-01',
        ]);

        tenancy()->initialize($tenantId);
        $customer = DB::table('contacts')->where('name', 'Actual Customer')->first();
        $this->assertNotNull($customer);
        tenancy()->end();

        // Attempting to access customer #1 via suppliers route -> 404
        $this->actingAs($admin)
            ->get(route('company.contacts.edit', ['type' => 'suppliers', 'id' => $customer->id]))
            ->assertStatus(404);

        $this->actingAs($admin)
            ->put(route('company.contacts.update', ['type' => 'suppliers', 'id' => $customer->id]), [
                'name' => 'Spoofed Supplier Name',
            ])
            ->assertStatus(404);

        $this->actingAs($admin)
            ->delete(route('company.contacts.destroy', ['type' => 'suppliers', 'id' => $customer->id]))
            ->assertStatus(404);
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyUser(string $companyRole): array
    {
        $id = uniqid('hard_');
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Hardening Test Corp',
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
        $id = uniqid('hbr_');
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Hardening Multi Branch Corp',
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
