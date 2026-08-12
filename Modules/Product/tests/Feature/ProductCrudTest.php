<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class ProductCrudTest extends TestCase
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

    public function test_company_member_can_manage_products(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // Setup: Create category, brand, uom
        tenancy()->initialize($tenantId);
        $suffix = uniqid();
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Electronics-'.$suffix,
            'is_active' => true,
        ]);
        $brandId = DB::table('brands')->insertGetId([
            'name' => 'Samsung-'.$suffix,
            'is_active' => true,
        ]);
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'Piece-'.$suffix,
            'code' => 'PCS-'.$suffix,
            'is_active' => true,
        ]);
        tenancy()->end();

        $this->actingAs($user)
            ->get(route('product.products.index'))
            ->assertStatus(200);

        // Create Product
        $codeLaptop = 'LAPTOP-'.$suffix;
        $this->actingAs($user)->post(route('product.products.store'), [
            'code' => $codeLaptop,
            'name' => 'Laptop Pro',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'uom_id' => $uomId,
            'description' => 'High-end laptop',
            'is_active' => true,
        ])->assertRedirect(route('product.products.index'));

        tenancy()->initialize($tenantId);
        $product = DB::table('products')->where('code', $codeLaptop)->first();
        $this->assertNotNull($product);
        $this->assertSame('Laptop Pro', $product->name);
        $this->assertSame($codeLaptop, $product->code);
        $this->assertSame($categoryId, $product->category_id);
        $this->assertSame($brandId, $product->brand_id);
        $this->assertSame($uomId, $product->uom_id);
        $this->assertTrue((bool) $product->is_active);
        tenancy()->end();

        // Update Product
        $this->actingAs($user)->put(route('product.products.update', ['product' => $product->id]), [
            'code' => 'LAPTOP-002',
            'name' => 'Laptop Pro Max',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'uom_id' => $uomId,
            'description' => 'Updated description',
            'is_active' => false,
        ])->assertRedirect(route('product.products.index'));

        tenancy()->initialize($tenantId);
        $productUpdated = DB::table('products')->where('id', $product->id)->first();
        $this->assertSame('Laptop Pro Max', $productUpdated->name);
        $this->assertSame('LAPTOP-002', $productUpdated->code);
        $this->assertFalse((bool) $productUpdated->is_active);
        tenancy()->end();

        // Delete Product (no variants yet)
        $this->actingAs($user)->delete(route('product.products.destroy', ['product' => $product->id]))
            ->assertRedirect();

        tenancy()->initialize($tenantId);
        $productDeleted = DB::table('products')->where('id', $product->id)->first();
        $this->assertNotNull($productDeleted->deleted_at);
        tenancy()->end();
    }

    public function test_partial_unique_index_code_reuse_after_soft_delete(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);
        $suffix = uniqid();
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Cat-'.$suffix, 'is_active' => true]);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Brand-'.$suffix, 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'Piece-'.$suffix, 'code' => 'PCS-'.$suffix, 'is_active' => true]);
        tenancy()->end();

        // Create first product
        $this->actingAs($user)->post(route('product.products.store'), [
            'code' => 'SAME-CODE',
            'name' => 'Product 1',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'uom_id' => $uomId,
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $product1 = DB::table('products')->where('code', 'SAME-CODE')->first();
        $product1Id = $product1->id;
        tenancy()->end();

        // Soft delete first product
        $this->actingAs($user)->delete(route('product.products.destroy', ['product' => $product1Id]))
            ->assertRedirect();

        // Create second product with same code (should work because of partial unique index)
        $this->actingAs($user)->post(route('product.products.store'), [
            'code' => 'SAME-CODE',
            'name' => 'Product 2',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'uom_id' => $uomId,
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $product2 = DB::table('products')->where('code', 'SAME-CODE')->where('id', '!=', $product1Id)->first();
        $this->assertNotNull($product2);
        $this->assertSame('Product 2', $product2->name);
        tenancy()->end();
    }

    public function test_cannot_delete_product_with_active_variants(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);
        $suffix = uniqid();
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Cat-'.$suffix, 'is_active' => true]);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Brand-'.$suffix, 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'Piece-'.$suffix, 'code' => 'PCS-'.$suffix, 'is_active' => true]);
        $productId = DB::table('products')->insertGetId([
            'code' => 'PROD-001-'.$suffix,
            'name' => 'Product with variant',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'uom_id' => $uomId,
            'is_active' => true,
        ]);
        DB::table('product_variants')->insert([
            'product_id' => $productId,
            'sku' => 'VAR-001-'.$suffix,
            'variant_name' => 'Variant 1',
            'is_active' => true,
        ]);
        tenancy()->end();

        // Try to delete product with active variant
        $this->actingAs($user)->delete(route('product.products.destroy', ['product' => $productId]))
            ->assertRedirect()
            ->assertSessionHas('error', 'Cannot delete product with active variants.');

        tenancy()->initialize($tenantId);
        $this->assertDatabaseHas('products', ['id' => $productId]);
        tenancy()->end();
    }

    public function test_search_and_filter_products(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);
        $suffix = uniqid();
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Electronics-'.$suffix, 'is_active' => true]);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Samsung-'.$suffix, 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'Piece-'.$suffix, 'code' => 'PCS-'.$suffix, 'is_active' => true]);
        tenancy()->end();

        // Create multiple products
        $this->actingAs($user)->post(route('product.products.store'), [
            'code' => 'PHONE-001',
            'name' => 'Smartphone',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'uom_id' => $uomId,
        ]);
        $this->actingAs($user)->post(route('product.products.store'), [
            'code' => 'LAPTOP-001',
            'name' => 'Laptop',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'uom_id' => $uomId,
        ]);

        // Test search by name
        $response = $this->actingAs($user)->get(route('product.products.index', ['search' => 'Smartphone']));
        $response->assertStatus(200);

        // Test filter by category
        $response = $this->actingAs($user)->get(route('product.products.index', ['category_id' => $categoryId]));
        $response->assertStatus(200);

        // Test filter by brand
        $response = $this->actingAs($user)->get(route('product.products.index', ['brand_id' => $brandId]));
        $response->assertStatus(200);
    }

    private function createCompanyWithMember(): array
    {
        $id = uniqid('prod_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Product Test Corp',
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
