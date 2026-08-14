<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class ProductCategoryCrudTest extends TestCase
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

    public function test_company_member_can_manage_categories(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // 1. Get List (Empty)
        $this->actingAs($user)
            ->get(route('product.categories.index'))
            ->assertStatus(200);

        // 2. Create Category
        $this->actingAs($user)->post(route('product.categories.store'), [
            'name' => 'Electronics',
            'is_active' => true,
        ])->assertRedirect(route('product.categories.index'));

        // Verify in Tenant DB
        tenancy()->initialize($tenantId);
        $category = DB::table('product_categories')->where('name', 'Electronics')->first();
        $this->assertNotNull($category);
        $this->assertSame('Electronics', $category->name);
        $this->assertTrue((bool) $category->is_active);
        tenancy()->end();

        // 3. Update Category
        $this->actingAs($user)->put(route('product.categories.update', ['category' => $category->id]), [
            'name' => 'Electronics Updated',
            'is_active' => false,
        ])->assertRedirect(route('product.categories.index'));

        tenancy()->initialize($tenantId);
        $categoryUpdated = DB::table('product_categories')->where('id', $category->id)->first();
        $this->assertSame('Electronics Updated', $categoryUpdated->name);
        $this->assertFalse((bool) $categoryUpdated->is_active);
        tenancy()->end();

        // 4. Delete Category
        $this->actingAs($user)->delete(route('product.categories.destroy', ['category' => $category->id]))
            ->assertRedirect();

        tenancy()->initialize($tenantId);
        $this->assertDatabaseMissing('product_categories', ['id' => $category->id]);
        tenancy()->end();
    }

    public function test_create_and_edit_pages_render(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)
            ->get(route('product.categories.create'))
            ->assertStatus(200);

        $this->actingAs($user)->post(route('product.categories.store'), [
            'name' => 'Test Category',
        ])->assertRedirect(route('product.categories.index'));

        tenancy()->initialize($tenantId);
        $category = DB::table('product_categories')->where('name', 'Test Category')->first();
        tenancy()->end();

        $this->actingAs($user)
            ->get(route('product.categories.edit', ['category' => $category->id]))
            ->assertStatus(200);
    }

    public function test_unique_name_validation(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($user)->post(route('product.categories.store'), [
            'name' => 'Duplicate Category',
        ])->assertRedirect();

        $this->actingAs($user)->post(route('product.categories.store'), [
            'name' => 'Duplicate Category',
        ])->assertSessionHasErrors(['name']);
    }

    public function test_non_member_cannot_manage_categories(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();
        $stranger = User::factory()->create(['email' => 'stranger@example.test', 'role' => 'user']);

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        $this->actingAs($stranger)
            ->get(route('product.categories.index'))
            ->assertStatus(403);

        $this->actingAs($stranger)
            ->get(route('product.categories.create'))
            ->assertStatus(403);
    }

    public function test_cannot_delete_category_still_used_by_products(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);
        $suffix = uniqid();
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Cat-'.$suffix, 'is_active' => true]);
        DB::table('products')->insert([
            'code' => 'PROD-'.$suffix,
            'name' => 'Product using category',
            'category_id' => $categoryId,
            'uom_id' => DB::table('uoms')->insertGetId(['name' => 'Piece-'.$suffix, 'code' => 'PCS-'.$suffix, 'is_active' => true]),
            'is_active' => true,
        ]);
        tenancy()->end();

        $this->actingAs($user)->delete(route('product.categories.destroy', ['category' => $categoryId]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Cannot delete category still used by products.');

        tenancy()->initialize($tenantId);
        $this->assertDatabaseHas('product_categories', ['id' => $categoryId]);
        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('product_cat_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Product Category Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        [$branchId, $member] = $this->provision($tenant, 'member_'.$id.'@acme.test');

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
            DB::statement('DROP SCHEMA IF EXISTS "'.$schemaName.'" CASCADE');
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
