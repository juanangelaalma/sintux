<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class TenantAuthTest extends TestCase
{
    private const SCHEMA_NAME = 'company_test_auth';

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        DB::statement('DROP SCHEMA IF EXISTS "'.self::SCHEMA_NAME.'" CASCADE');

        // Clean tables
        DB::table('company_users')->delete();
        DB::table('domains')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();
    }

    public function test_user_can_login_and_resolves_tenant()
    {
        // 1. Setup data
        $user = User::factory()->create([
            'email' => 'auth-test@example.com',
            'password' => bcrypt('secret-pass'),
            'role' => 'user',
        ]);

        $tenant = Tenant::create([
            'id' => 'test-tenant',
            'name' => 'Test Company',
            'schema_name' => self::SCHEMA_NAME,
        ]);

        CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'is_default' => true,
        ]);

        // Run migrations inside tenant schema
        tenancy()->initialize($tenant);
        DB::purge('tenant');
        $this->artisan('tenants:migrate');

        DB::table('branches')->insert([
            'name' => 'HQ Branch',
            'code' => 'HQ-TEST',
            'is_headquarters' => true,
        ]);
        tenancy()->end();

        // 2. Perform Login Request
        $response = $this->post('/login', [
            'email' => 'auth-test@example.com',
            'password' => 'secret-pass',
        ]);

        // 3. Verify Response and Session
        $response->assertRedirect('/dashboard');
        $this->assertEquals('test-tenant', session('active_tenant_id'));

        // 4. Verify Active Tenancy on subsequent request
        $dashboardResponse = $this->actingAs($user)->get('/dashboard');
        $dashboardResponse->assertStatus(200);
        $this->assertEquals('test-tenant', session('active_tenant_id'));

        // Tenancy must be cleaned up after the response so the default
        // connection reverts to central (no leakage between requests).
        $this->assertFalse(tenancy()->initialized);
        $this->assertSame('pgsql', config('database.default'));

        // Cleanup schema
        $tenant->database()->manager()->deleteDatabase($tenant);
    }
}
