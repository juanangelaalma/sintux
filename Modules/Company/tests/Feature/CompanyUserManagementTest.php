<?php

namespace Modules\Company\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Models\CompanyUser;
use Modules\Company\Models\CompanyUserRole;
use Modules\Company\Models\Role;
use Tests\TestCase;

class CompanyUserManagementTest extends TestCase
{
    private const SCHEMA_NAME = 'company_users_test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);

        DB::table('company_users')->delete();
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

    public function test_admin_with_permission_can_manage_company_users(): void
    {
        [$tenantId, $branchId, $admin] = $this->createCompanyWithAdmin();

        tenancy()->initialize($tenantId);
        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB',
            'is_active' => true,
        ]);
        tenancy()->end();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($admin)->get(route('company.users.index'))->assertStatus(200);

        // Create
        $this->actingAs($admin)->post(route('company.users.store'), [
            'name' => 'Branch Staff',
            'email' => 'staff@acme.test',
            'password' => 'secret-password',
            'company_role' => 'member',
            'branch_id' => $branchBId,
            'roles' => [Role::where('slug', 'sales_admin')->value('id')],
        ])->assertRedirect(route('company.users.index'));

        $staff = User::where('email', 'staff@acme.test')->first();
        $this->assertNotNull($staff, 'Staff user should be created in the central schema.');

        $membership = CompanyUser::where('user_id', $staff->id)->where('tenant_id', $tenantId)->first();
        $this->assertNotNull($membership);
        $this->assertSame('member', $membership->role);
        $this->assertSame($branchBId, $membership->branch_id);

        $this->assertDatabaseHas('company_user_roles', [
            'company_user_id' => $membership->id,
            'role_id' => Role::where('slug', 'sales_admin')->value('id'),
            'branch_id' => null,
        ]);

        // Update
        $this->actingAs($admin)->put(route('company.users.update', $membership->id), [
            'name' => 'Branch Staff Updated',
            'company_role' => 'admin',
            'branch_id' => $branchId,
            'roles' => [],
        ])->assertRedirect(route('company.users.index'));

        $this->assertSame('Branch Staff Updated', $staff->fresh()->name);
        $this->assertSame('admin', $membership->fresh()->role);
        $this->assertSame($branchId, $membership->fresh()->branch_id);
        $this->assertDatabaseMissing('company_user_roles', ['company_user_id' => $membership->id]);

        // Delete
        $this->actingAs($admin)->delete(route('company.users.destroy', $membership->id))
            ->assertRedirect(route('company.users.index'));

        $this->assertDatabaseMissing('company_users', ['id' => $membership->id]);
        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
    }

    public function test_owner_can_manage_company_users(): void
    {
        [$tenantId, $branchId, $owner] = $this->createCompanyWithOwner();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($owner)
            ->get(route('company.users.index'))
            ->assertStatus(200);
    }

    public function test_member_without_permission_can_view_but_not_mutate_company_users(): void
    {
        [$tenantId, $branchId, $member] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($member)
            ->get(route('company.users.index'))
            ->assertStatus(200);

        $this->actingAs($member)
            ->post(route('company.users.store'), [
                'name' => 'Should Not Exist',
                'email' => 'forbidden@acme.test',
                'password' => 'secret-password',
                'company_role' => 'member',
                'branch_id' => $branchId,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('users', ['email' => 'forbidden@acme.test']);

        $membershipId = CompanyUser::where('user_id', $member->id)
            ->where('tenant_id', $tenantId)
            ->value('id');

        $this->actingAs($member)
            ->delete(route('company.users.destroy', $membershipId))
            ->assertStatus(403);

        $this->assertDatabaseHas('company_users', ['id' => $membershipId]);
    }

    public function test_user_can_be_assigned_to_multiple_allowed_branches(): void
    {
        [$tenantId, $hqBranchId, $admin] = $this->createCompanyWithAdmin();

        tenancy()->initialize($tenantId);
        $branchBId = (int) (DB::table('branches')->where('code', 'BRB')->value('id') ?? DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB',
            'is_active' => true,
        ]));
        tenancy()->end();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $this->actingAs($admin)->post(route('company.users.store'), [
            'name' => 'Multi Branch Staff',
            'email' => 'multi@acme.test',
            'password' => 'secret-password',
            'company_role' => 'member',
            'branch_id' => $branchBId,
            'allowed_branch_ids' => [$hqBranchId],
            'roles' => [Role::where('slug', 'sales_admin')->value('id')],
        ])->assertRedirect(route('company.users.index'));

        $staff = User::where('email', 'multi@acme.test')->first();
        $this->assertNotNull($staff);

        $membership = CompanyUser::where('user_id', $staff->id)->where('tenant_id', $tenantId)->first();
        $this->assertNotNull($membership);
        $this->assertSame($branchBId, $membership->branch_id);

        $this->assertEqualsCanonicalizing(
            [$branchBId, $hqBranchId],
            $membership->allowedBranchIds(),
        );

        $this->assertDatabaseHas('company_user_roles', [
            'company_user_id' => $membership->id,
            'role_id' => Role::where('slug', 'sales_admin')->value('id'),
            'branch_id' => null,
        ]);
    }

    public function test_user_can_switch_to_allowed_branch_only(): void
    {
        [$tenantId, $hqBranchId, $admin] = $this->createCompanyWithAdmin();

        tenancy()->initialize($tenantId);
        $branchBId = (int) (DB::table('branches')->where('code', 'BRB')->value('id') ?? DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB',
            'is_active' => true,
        ]));
        $branchCId = (int) (DB::table('branches')->where('code', 'BRC')->value('id') ?? DB::table('branches')->insertGetId([
            'name' => 'Branch C',
            'code' => 'BRC',
            'is_active' => true,
        ]));
        tenancy()->end();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $this->actingAs($admin)->post(route('company.users.store'), [
            'name' => 'Scoped Staff',
            'email' => 'scoped@acme.test',
            'password' => 'secret-password',
            'company_role' => 'member',
            'branch_id' => $branchBId,
            'allowed_branch_ids' => [$branchBId],
        ])->assertRedirect(route('company.users.index'));

        $staff = User::where('email', 'scoped@acme.test')->first();
        $this->assertNotNull($staff);

        $this->actingAs($staff)
            ->post(route('company.branches.switch'), ['branch_id' => $branchBId])
            ->assertRedirect();

        $this->assertSame($branchBId, (int) session('active_branch_id'));

        $this->actingAs($staff)
            ->post(route('company.branches.switch'), ['branch_id' => $branchCId])
            ->assertForbidden();
    }

    public function test_switching_to_hq_sets_all_scope(): void
    {
        [$tenantId, $hqBranchId, $admin] = $this->createCompanyWithAdmin();

        tenancy()->initialize($tenantId);
        $branchBId = (int) (DB::table('branches')->where('code', 'BRB')->value('id') ?? DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB',
            'is_active' => true,
        ]));
        tenancy()->end();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        $this->actingAs($admin)
            ->post(route('company.branches.switch'), ['branch_id' => $hqBranchId])
            ->assertRedirect();

        $this->assertSame('all', session('branch_scope'));
        $this->assertSame($hqBranchId, (int) session('active_branch_id'));
    }

    public function test_session_branch_is_sanitized_for_branch_scoped_user(): void
    {
        [$tenantId, $hqBranchId, $admin] = $this->createCompanyWithAdmin();

        tenancy()->initialize($tenantId);
        $branchBId = (int) (DB::table('branches')->where('code', 'BRB')->value('id') ?? DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB',
            'is_active' => true,
        ]));
        tenancy()->end();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $this->actingAs($admin)->post(route('company.users.store'), [
            'name' => 'Scoped Staff',
            'email' => 'scoped2@acme.test',
            'password' => 'secret-password',
            'company_role' => 'member',
            'branch_id' => $branchBId,
            'allowed_branch_ids' => [$branchBId],
        ])->assertRedirect(route('company.users.index'));

        $staff = User::where('email', 'scoped2@acme.test')->first();

        session(['active_branch_id' => $hqBranchId]);

        $this->actingAs($staff)->get(route('company.users.index'))->assertOk();

        $this->assertSame($branchBId, (int) session('active_branch_id'));
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyWithAdmin(): array
    {
        $id = uniqid('user_mgmt_');
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'User Mgmt Corp',
            'schema_name' => 'sch_'.$id,
            'is_active' => true,
        ]);

        [$branchId, $admin] = $this->provision($tenant, 'admin_'.$id.'@acme.test');

        $membership = CompanyUser::where('user_id', $admin->id)->where('tenant_id', $tenant->id)->first();
        CompanyUserRole::create([
            'company_user_id' => $membership->id,
            'branch_id' => null,
            'role_id' => Role::where('slug', 'admin')->value('id'),
        ]);

        return [$tenant->id, $branchId, $admin];
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyWithOwner(): array
    {
        $id = uniqid('owner_');
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Owner Corp',
            'schema_name' => 'sch_'.$id,
            'is_active' => true,
        ]);

        [$branchId, $owner] = $this->provision($tenant, 'owner_'.$id.'@acme.test');

        return [$tenant->id, $branchId, $owner];
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('member_');
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Member Corp',
            'schema_name' => 'sch_'.$id,
            'is_active' => true,
        ]);

        [$branchId, $member] = $this->provision($tenant, 'member_'.$id.'@acme.test');

        CompanyUser::where('user_id', $member->id)
            ->where('tenant_id', $tenant->id)
            ->update(['role' => 'member', 'branch_id' => $branchId]);

        return [$tenant->id, $branchId, $member];
    }

    /**
     * Create a tenant, its schema & HQ branch, then provision a user as owner.
     *
     * @return array{0: int, 1: User}
     */
    private function provision(Tenant $tenant, string $email): array
    {
        tenancy()->initialize($tenant);
        $existingHq = DB::table('branches')->where('code', 'HQ')->value('id');
        $branchId = $existingHq ? (int) $existingHq : DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
        ]);
        tenancy()->end();

        $user = User::factory()->create(['email' => $email, 'role' => 'user']);

        CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'branch_id' => $branchId,
            'role' => 'owner',
            'is_default' => true,
        ]);

        return [$branchId, $user];
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
