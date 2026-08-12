<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class BrandCrudTest extends TestCase
{
    /**
     * Schema tenant dinamis yang dibuat oleh test ini (sch_brand_xxxxx...).
     * Diisi di createCompanyWithMember() supaya tearDown() bisa men-drop
     * schema yang BENAR-BENAR dipakai, bukan nama statis yang tidak pernah dipakai.
     */
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('company_users')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();

        // Sapu bersih schema-schema tenant sisa dari run test sebelumnya yang
        // mungkin gagal di tengah jalan (tearDown tidak sempat jalan) sehingga
        // schema-nya numpuk dan mencemari unique-check di run berikutnya.
        $this->dropLeftoverSchemas();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
        }

        parent::tearDown();
    }

    public function test_company_member_can_manage_brands(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)
            ->get(route('product.brands.index'))
            ->assertStatus(200);

        $this->actingAs($user)->post(route('product.brands.store'), [
            'name' => 'Samsung',
            'is_active' => true,
        ])->assertRedirect(route('product.brands.index'));

        tenancy()->initialize($tenantId);
        $brand = DB::table('brands')->where('name', 'Samsung')->first();
        $this->assertNotNull($brand);
        $this->assertSame('Samsung', $brand->name);
        $this->assertTrue((bool) $brand->is_active);
        tenancy()->end();

        $this->actingAs($user)->put(route('product.brands.update', ['brand' => $brand->id]), [
            'name' => 'Samsung Electronics',
            'is_active' => false,
        ])->assertRedirect(route('product.brands.index'));

        tenancy()->initialize($tenantId);
        $brandUpdated = DB::table('brands')->where('id', $brand->id)->first();
        $this->assertSame('Samsung Electronics', $brandUpdated->name);
        $this->assertFalse((bool) $brandUpdated->is_active);
        tenancy()->end();

        $this->actingAs($user)->delete(route('product.brands.destroy', ['brand' => $brand->id]))
            ->assertRedirect();

        tenancy()->initialize($tenantId);
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
        tenancy()->end();
    }

    public function test_unique_name_validation(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)->post(route('product.brands.store'), [
            'name' => 'Apple',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('product.brands.store'), [
            'name' => 'Apple',
        ])->assertSessionHasErrors(['name']);
    }

    private function createCompanyWithMember(): array
    {
        $id = uniqid('brand_');
        $schemaName = 'sch_' . $id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Brand Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        [$branchId, $member] = $this->provision($tenant, 'member_' . $id . '@acme.test');

        return [$tenant->id, $branchId, $member];
    }

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
            'role' => 'member',
            'is_default' => true,
        ]);

        return [$branchId, $user];
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            (new \PDO('pgsql:host=127.0.0.1;port=5432;dbname=testing;user=root;password='))
                ->exec('DROP SCHEMA IF EXISTS "' . $schemaName . '" CASCADE');
        } catch (\Exception $e) {
        }
    }

    /**
     * Drop semua schema tenant dengan prefix "sch_brand_" yang mungkin
     * ketinggalan dari run test sebelumnya (misal karena test gagal/exit
     * sebelum tearDown sempat jalan). Tanpa ini, schema-schema lama akan
     * terus numpuk dan mencemari data (mis. unique-check "name") di run berikutnya.
     */
    private function dropLeftoverSchemas(): void
    {
        try {
            $pdo = new \PDO('pgsql:host=127.0.0.1;port=5432;dbname=testing;user=root;password=');
            $stmt = $pdo->query(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'sch\\_brand\\_%' ESCAPE '\\'"
            );
            foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $schemaName) {
                $pdo->exec('DROP SCHEMA IF EXISTS "' . $schemaName . '" CASCADE');
            }
        } catch (\Exception $e) {
        }
    }
}