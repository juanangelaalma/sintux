<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class UomCrudTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();

        DB::table('company_user_branches')->delete();
        DB::table('company_users')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();
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

        $this->dropLeftoverSchemas();

        parent::tearDown();
    }

    public function test_company_member_can_manage_uoms(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)
            ->get(route('product.uoms.index'))
            ->assertStatus(200);

        $this->actingAs($user)->post(route('product.uoms.store'), [
            'name' => 'Piece',
            'code' => 'PCS',
            'is_active' => true,
        ])->assertRedirect(route('product.uoms.index'));

        tenancy()->initialize($tenantId);
        $uom = DB::table('uoms')->where('code', 'PCS')->first();
        $this->assertNotNull($uom);
        $this->assertSame('Piece', $uom->name);
        $this->assertSame('PCS', $uom->code);
        $this->assertTrue((bool) $uom->is_active);
        tenancy()->end();

        $this->actingAs($user)->put(route('product.uoms.update', ['uom' => $uom->id]), [
            'name' => 'Pieces',
            'code' => 'PC',
            'is_active' => false,
        ])->assertRedirect(route('product.uoms.index'));

        tenancy()->initialize($tenantId);
        $uomUpdated = DB::table('uoms')->where('id', $uom->id)->first();
        $this->assertSame('Pieces', $uomUpdated->name);
        $this->assertSame('PC', $uomUpdated->code);
        $this->assertFalse((bool) $uomUpdated->is_active);
        tenancy()->end();

        $this->actingAs($user)->delete(route('product.uoms.destroy', ['uom' => $uom->id]))
            ->assertRedirect();

        tenancy()->initialize($tenantId);
        $this->assertDatabaseMissing('uoms', ['id' => $uom->id]);
        tenancy()->end();
    }

    public function test_unique_code_validation(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)->post(route('product.uoms.store'), [
            'name' => 'Box',
            'code' => 'BOX',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('product.uoms.store'), [
            'name' => 'Box 2',
            'code' => 'BOX',
        ])->assertSessionHasErrors(['code']);
    }

    private function createCompanyWithMember(): array
    {
        $id = uniqid('uom_');
        $schemaName = 'sch_' . $id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Uom Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        \Illuminate\Support\Facades\Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

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
            DB::statement('DROP SCHEMA IF EXISTS "' . $schemaName . '" CASCADE');
        } catch (\Exception $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('public', 'information_schema') AND schema_name NOT LIKE 'pg_%'"
            );
            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;
                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Exception $e) {
        }
    }
}