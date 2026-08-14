<?php

namespace Modules\Contact\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Company\Application\CreateCompanyUser;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Tests\Support\CompanyTestFixture;
use Tests\TestCase;

class ContactAuthorizationTest extends TestCase
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

    public function test_user_without_contact_permissions_is_forbidden(): void
    {
        [$tenantId, $branchId, $member] = $this->createCompanyUser('member');

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // GET index -> 403
        $this->actingAs($member)
            ->get(route('company.contacts.index', 'customers'))
            ->assertStatus(403);

        // GET create -> 403
        $this->actingAs($member)
            ->get(route('company.contacts.create', 'customers'))
            ->assertStatus(403);

        // POST store -> 403
        $this->actingAs($member)
            ->post(route('company.contacts.store', 'customers'), [
                'branch_id' => $branchId,
                'name' => 'Unauthorized Customer',
                'registered_at' => '2026-08-01',
            ])
            ->assertStatus(403);

        // GET edit -> 403
        $this->actingAs($member)
            ->get(route('company.contacts.edit', ['type' => 'customers', 'id' => 1]))
            ->assertStatus(403);

        // PUT update -> 403
        $this->actingAs($member)
            ->put(route('company.contacts.update', ['type' => 'customers', 'id' => 1]), [
                'name' => 'Updated Name',
            ])
            ->assertStatus(403);

        // DELETE destroy -> 403
        $this->actingAs($member)
            ->delete(route('company.contacts.destroy', ['type' => 'customers', 'id' => 1]))
            ->assertStatus(403);
    }

    public function test_user_with_admin_role_can_access_all_contact_endpoints(): void
    {
        [$tenantId, $branchId, $admin] = $this->createCompanyUser('admin');

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // GET index -> 200
        $this->actingAs($admin)
            ->get(route('company.contacts.index', 'customers'))
            ->assertStatus(200);

        // GET create -> 200
        $this->actingAs($admin)
            ->get(route('company.contacts.create', 'customers'))
            ->assertStatus(200);

        // POST store -> 302
        $this->actingAs($admin)
            ->post(route('company.contacts.store', 'customers'), [
                'branch_id' => $branchId,
                'name' => 'Authorized Customer',
                'registered_at' => '2026-08-01',
            ])
            ->assertRedirect(route('company.contacts.index', 'customers'));
    }

    public function test_list_index_projection_hides_sensitive_pii_fields(): void
    {
        [$tenantId, $branchId, $admin] = $this->createCompanyUser('admin');

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // Create a contact with full PII data
        $this->actingAs($admin)->post(route('company.contacts.store', 'customers'), [
            'branch_id' => $branchId,
            'name' => 'PII Customer',
            'registered_at' => '2026-08-01',
            'identity_type' => 'KTP',
            'identity_number' => '3171234567890001',
            'npwp' => '01.234.567.8-901.000',
            'notes' => 'Secret note',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
            'billing_address' => [
                'detail' => 'Jl. PII Secret No. 12',
            ],
        ]);

        // Check index props: list projection must NOT contain PII fields
        $this->actingAs($admin)
            ->get(route('company.contacts.index', 'customers'))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contact/Customers/index')
                ->has('contacts', 1)
                ->has('contacts.0', fn (Assert $json) => $json
                    ->where('name', 'PII Customer')
                    ->where('id', 1)
                    ->where('branch_id', $branchId)
                    ->where('type', 'customer')
                    ->where('registered_at', '2026-08-01')
                    ->where('is_active', true)
                    ->missing('identity_number')
                    ->missing('identity_type')
                    ->missing('npwp')
                    ->missing('notes')
                    ->missing('bank_name')
                    ->missing('bank_account_number')
                    ->missing('billing_address')
                    ->missing('shipping_address')
                    ->etc()
                )
            );
    }

    public function test_edit_view_includes_full_detail_projection_with_pii(): void
    {
        [$tenantId, $branchId, $admin] = $this->createCompanyUser('admin');

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($admin)->post(route('company.contacts.store', 'customers'), [
            'branch_id' => $branchId,
            'name' => 'Full Detail Customer',
            'registered_at' => '2026-08-01',
            'identity_type' => 'KTP',
            'identity_number' => '3171234567890001',
            'npwp' => '01.234.567.8-901.000',
            'bank_name' => 'BCA',
            'bank_account_number' => '1234567890',
        ]);

        // Fetch edit view: detail projection MUST contain full detail
        $this->actingAs($admin)
            ->get(route('company.contacts.edit', ['type' => 'customers', 'id' => 1]))
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contact/edit')
                ->has('contact', fn (Assert $json) => $json
                    ->where('name', 'Full Detail Customer')
                    ->where('identity_type', 'KTP')
                    ->where('identity_number', '3171234567890001')
                    ->where('npwp', '01.234.567.8-901.000')
                    ->where('bank_name', 'BCA')
                    ->where('bank_account_number', '1234567890')
                    ->etc()
                )
            );
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyUser(string $companyRole): array
    {
        $id = uniqid('auth_');
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Auth Test Corp',
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
