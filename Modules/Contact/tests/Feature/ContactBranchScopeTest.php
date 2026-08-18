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
    private const SCHEMA_NAME = 'contact_scope_test_schema';

    protected function setUp(): void
    {
        parent::setUp();

        CompanyTestFixture::resetMemberships();
        $this->seed(RolePermissionSeeder::class);
        DB::table('tenants')->delete();
        DB::table('users')->delete();

        $this->dropSchema();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropSchema();
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
            ->assertStatus(403);

        $this->actingAs($user)
            ->put(route('company.contacts.update', ['type' => 'customers', 'id' => $foreignId]), ['name' => 'Nope'])
            ->assertStatus(403);

        $this->actingAs($user)
            ->delete(route('company.contacts.destroy', ['type' => 'customers', 'id' => $foreignId]))
            ->assertStatus(403);
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

    private function dropSchema(): void
    {
        try {
            (new \PDO('pgsql:host=127.0.0.1;port=5432;dbname=testing;user=root;password='))
                ->exec('DROP SCHEMA IF EXISTS "'.self::SCHEMA_NAME.'" CASCADE');
        } catch (\Exception $e) {
        }
    }
}
