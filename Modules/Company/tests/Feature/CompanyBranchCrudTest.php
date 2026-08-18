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

class CompanyBranchCrudTest extends TestCase
{
    private const SCHEMA_NAME = 'company_branches_test';

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

    public function test_admin_with_permission_can_manage_company_branches(): void
    {
        [$tenantId, $hqId, $admin] = $this->createCompanyWithAdmin();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqId]);

        $this->actingAs($admin)->get(route('company.branches.index'))->assertStatus(200);

        // Create
        $this->actingAs($admin)->post(route('company.branches.store'), [
            'name' => 'Jakarta Branch',
            'code' => 'JKT',
            'address' => 'Jl. Sudirman No. 1',
            'phone' => '021-123456',
            'is_active' => true,
        ])->assertRedirect(route('company.branches.index'));

        tenancy()->initialize($tenantId);
        $branch = DB::table('branches')->where('code', 'JKT')->first();
        $this->assertNotNull($branch, 'Branch should be created in the tenant schema.');
        $this->assertSame('Jakarta Branch', $branch->name);
        $this->assertSame('Jl. Sudirman No. 1', $branch->address);
        $this->assertSame('021-123456', $branch->phone);
        $this->assertSame(1, $this->branchFlag($branch->is_active));
        tenancy()->end();

        // Update + deactivate
        $this->actingAs($admin)->put(route('company.branches.update', $branch->id), [
            'name' => 'Jakarta Utara',
            'code' => 'JKT',
            'is_active' => false,
        ])->assertRedirect(route('company.branches.index'));

        tenancy()->initialize($tenantId);
        $updated = DB::table('branches')->where('id', $branch->id)->first();
        $this->assertSame('Jakarta Utara', $updated->name);
        $this->assertSame('JKT', $updated->code);
        $this->assertSame(0, $this->branchFlag($updated->is_active));
        tenancy()->end();
    }

    public function test_branch_code_must_be_unique_within_tenant(): void
    {
        [$tenantId, $branchId, $admin] = $this->createCompanyWithAdmin();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // HQ branch already uses code 'HQ'
        $this->actingAs($admin)
            ->post(route('company.branches.store'), [
                'name' => 'Duplicate Branch',
                'code' => 'HQ',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_owner_can_manage_company_branches(): void
    {
        [$tenantId, $branchId, $owner] = $this->createCompanyWithOwner();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($owner)
            ->get(route('company.branches.index'))
            ->assertStatus(200);
    }

    public function test_member_without_permission_cannot_access_branches(): void
    {
        [$tenantId, $branchId, $member] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($member)
            ->get(route('company.branches.index'))
            ->assertStatus(403);

        $this->actingAs($member)
            ->post(route('company.branches.store'), [
                'name' => 'Forbidden Branch',
                'code' => 'NO',
            ])
            ->assertStatus(403);

        tenancy()->initialize($tenantId);
        $this->assertNull(DB::table('branches')->where('code', 'NO')->first());
        tenancy()->end();
    }

    public function test_headquarters_flag_is_read_only(): void
    {
        [$tenantId, $hqId, $admin] = $this->createCompanyWithAdmin();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqId]);

        // Creating a branch with is_headquarters=true must NOT create a second HQ.
        $this->actingAs($admin)->post(route('company.branches.store'), [
            'name' => 'Branch B',
            'code' => 'B',
            'is_headquarters' => true,
        ])->assertRedirect(route('company.branches.index'));

        tenancy()->initialize($tenantId);
        $branchB = DB::table('branches')->where('code', 'B')->first();
        $this->assertNotNull($branchB);
        $this->assertSame(0, $this->branchFlag($branchB->is_headquarters));
        tenancy()->end();

        // Trying to demote the HQ through update must be ignored.
        $this->actingAs($admin)->put(route('company.branches.update', $hqId), [
            'name' => 'HQ Renamed',
            'code' => 'HQ',
            'is_headquarters' => false,
        ])->assertRedirect(route('company.branches.index'));

        tenancy()->initialize($tenantId);
        $hq = DB::table('branches')->where('id', $hqId)->first();
        $this->assertSame('HQ Renamed', $hq->name);
        $this->assertSame(1, $this->branchFlag($hq->is_headquarters));
        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyWithAdmin(): array
    {
        $tenant = Tenant::create([
            'id' => 'branch-mgmt-tenant',
            'name' => 'Branch Mgmt Corp',
            'schema_name' => self::SCHEMA_NAME,
            'is_active' => true,
        ]);

        [$branchId, $admin] = $this->provision($tenant, 'branch-admin@acme.test');

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
        $tenant = Tenant::create([
            'id' => 'branch-owner-tenant',
            'name' => 'Branch Owner Corp',
            'schema_name' => self::SCHEMA_NAME,
            'is_active' => true,
        ]);

        [$branchId, $owner] = $this->provision($tenant, 'branch-owner@acme.test');

        return [$tenant->id, $branchId, $owner];
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyWithMember(): array
    {
        $tenant = Tenant::create([
            'id' => 'branch-member-tenant',
            'name' => 'Branch Member Corp',
            'schema_name' => self::SCHEMA_NAME,
            'is_active' => true,
        ]);

        [$branchId, $member] = $this->provision($tenant, 'branch-member@acme.test');

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

    /**
     * Normalize a Postgres boolean (bool|int|string) to 0|1.
     */
    private function branchFlag(mixed $value): int
    {
        return in_array($value, [true, 1, '1', 't', 'true'], true) ? 1 : 0;
    }

    private function dropSchema(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS "'.self::SCHEMA_NAME.'" CASCADE');
    }
}
