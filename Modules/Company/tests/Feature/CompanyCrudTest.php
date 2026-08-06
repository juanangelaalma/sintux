<?php

namespace Modules\Company\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyCrudTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clean central database schemas to ensure test safety
        DB::table('company_users')->delete();
        DB::table('domains')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();

        // Force drop schema on both testing and sintux databases to prevent constraint blocks
        foreach (['testing', 'sintux'] as $dbName) {
            try {
                $pdo = new \PDO("pgsql:host=127.0.0.1;port=5432;dbname={$dbName};user=root;password=");
                $pdo->exec('DROP SCHEMA IF EXISTS "company_crud_tenant_test" CASCADE');
            } catch (\Exception $e) {
            }
        }
    }

    public function test_superadmin_can_perform_company_crud()
    {
        $superadmin = User::factory()->create([
            'email' => 'super@sintux.com',
            'role' => 'superadmin',
        ]);

        // 1. Read / Index
        $response = $this->actingAs($superadmin)->get(route('admin.companies.index'));
        $response->assertStatus(200);

        // 2. Store / Create Company (This creates schema, triggers migration and creates Admin User)
        $responseStore = $this->actingAs($superadmin)->post(route('admin.companies.store'), [
            'id' => 'crud-tenant',
            'name' => 'CRUD Corp',
            'schema_name' => 'company_crud_tenant_test',
            'is_active' => true,
            'admin_name' => 'Acme Admin',
            'admin_email' => 'acme_admin@sintux.com',
            'admin_password' => 'secret-password',
        ]);

        $responseStore->assertSessionHasNoErrors();

        $responseStore->assertRedirect(route('admin.companies.index'));
        $this->assertDatabaseHas('tenants', [
            'id' => 'crud-tenant',
            'schema_name' => 'company_crud_tenant_test',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'acme_admin@sintux.com',
            'name' => 'Acme Admin',
            'role' => 'admin',
        ]);

        $tenant = Tenant::find('crud-tenant');
        $this->assertNotNull($tenant);

        // Verify Branch inside tenant context
        tenancy()->initialize($tenant);
        $this->assertDatabaseHas('branches', [
            'code' => 'HQ',
            'is_headquarters' => true,
        ]);
        tenancy()->end();

        // 3. Update
        $responseUpdate = $this->actingAs($superadmin)->put(route('admin.companies.update', 'crud-tenant'), [
            'name' => 'CRUD Corp Updated',
            'is_active' => false,
        ]);
        $responseUpdate->assertRedirect(route('admin.companies.index'));
        $this->assertDatabaseHas('tenants', [
            'id' => 'crud-tenant',
            'name' => 'CRUD Corp Updated',
            'is_active' => false,
        ]);

        // 4. Destroy
        $responseDestroy = $this->actingAs($superadmin)->delete(route('admin.companies.destroy', 'crud-tenant'));
        $responseDestroy->assertRedirect(route('admin.companies.index'));
        $this->assertDatabaseMissing('tenants', [
            'id' => 'crud-tenant',
        ]);

        // Clean up schema on database
        try {
            $tenant->database()->manager()->deleteDatabase($tenant);
        } catch (\Exception $e) {
        }
    }

    public function test_regular_user_cannot_access_company_crud()
    {
        $user = User::factory()->create([
            'email' => 'regular@sintux.com',
            'role' => 'user',
        ]);

        $response = $this->actingAs($user)->get(route('admin.companies.index'));
        $response->assertRedirect('/dashboard');

        $responseStore = $this->actingAs($user)->post(route('admin.companies.store'), [
            'id' => 'crud-tenant-fail',
            'name' => 'Fail Corp',
            'schema_name' => 'company_fail',
            'is_active' => true,
            'admin_name' => 'Fail Admin',
            'admin_email' => 'fail_admin@sintux.com',
            'admin_password' => 'secret-password',
        ]);
        $responseStore->assertRedirect('/dashboard');
    }
}
