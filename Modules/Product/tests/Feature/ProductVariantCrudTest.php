<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class ProductVariantCrudTest extends TestCase
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

    public function test_company_member_can_manage_variants(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        // Setup: Create product
        tenancy()->initialize($tenantId);
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Electronics', 'is_active' => true]);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Samsung', 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'Piece', 'code' => 'PCS', 'is_active' => true]);
        $productId = DB::table('products')->insertGetId([
            'code' => 'LAPTOP-001',
            'name' => 'Laptop Pro',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'uom_id' => $uomId,
            'is_active' => true,
        ]);
        tenancy()->end();

        // Create Variant
        $this->actingAs($user)->post(route('product.products.variants.store', ['product' => $productId]), [
            'product_id' => $productId,
            'sku' => 'LAPTOP-001-BLK-16',
            'variant_name' => 'Black / 16GB',
            'attributes' => ['color' => 'Black', 'ram' => '16GB'],
            'is_active' => true,
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $variant = DB::table('product_variants')->where('sku', 'LAPTOP-001-BLK-16')->first();
        $this->assertNotNull($variant);
        $this->assertSame('LAPTOP-001-BLK-16', $variant->sku);
        $this->assertSame('Black / 16GB', $variant->variant_name);
        $this->assertSame(json_encode(['color' => 'Black', 'ram' => '16GB']), $variant->attributes);
        $this->assertTrue((bool) $variant->is_active);
        $variantId = $variant->id;
        tenancy()->end();

        // Update Variant
        $this->actingAs($user)->put(route('product.products.variants.update', ['product' => $productId, 'variant' => $variantId]), [
            'product_id' => $productId,
            'sku' => 'LAPTOP-001-SLV-32',
            'variant_name' => 'Silver / 32GB',
            'attributes' => ['color' => 'Silver', 'ram' => '32GB'],
            'is_active' => false,
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $variantUpdated = DB::table('product_variants')->where('id', $variantId)->first();
        $this->assertSame('LAPTOP-001-SLV-32', $variantUpdated->sku);
        $this->assertSame('Silver / 32GB', $variantUpdated->variant_name);
        $this->assertSame(json_encode(['color' => 'Silver', 'ram' => '32GB']), $variantUpdated->attributes);
        $this->assertFalse((bool) $variantUpdated->is_active);
        tenancy()->end();

        // Delete Variant
        $this->actingAs($user)->delete(route('product.products.variants.destroy', ['product' => $productId, 'variant' => $variantId]))
            ->assertRedirect();

        tenancy()->initialize($tenantId);
        $this->assertDatabaseMissing('product_variants', ['id' => $variantId]);
        tenancy()->end();
    }

    public function test_partial_unique_index_sku_reuse_after_soft_delete(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);
        $suffix = uniqid();
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Electronics-' . $suffix, 'is_active' => true]);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Samsung-' . $suffix, 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'Piece-' . $suffix, 'code' => 'PCS-' . $suffix, 'is_active' => true]);
        $productId = DB::table('products')->insertGetId([
            'code' => 'LAPTOP-' . $suffix,
            'name' => 'Laptop Pro',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'uom_id' => $uomId,
            'is_active' => true,
        ]);
        tenancy()->end();

        // Create first variant
        $this->actingAs($user)->post(route('product.products.variants.store', ['product' => $productId]), [
            'product_id' => $productId,
            'sku' => 'SAME-SKU',
            'variant_name' => 'Variant 1',
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $variant1 = DB::table('product_variants')->where('sku', 'SAME-SKU')->first();
        $variant1Id = $variant1->id;
        tenancy()->end();

        // Soft delete first variant
        $this->actingAs($user)->delete(route('product.products.variants.destroy', ['product' => $productId, 'variant' => $variant1Id]))
            ->assertRedirect();

        // Create second variant with same SKU (should work because of partial unique index)
        $this->actingAs($user)->post(route('product.products.variants.store', ['product' => $productId]), [
            'product_id' => $productId,
            'sku' => 'SAME-SKU',
            'variant_name' => 'Variant 2',
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $variant2 = DB::table('product_variants')->where('sku', 'SAME-SKU')->where('id', '!=', $variant1Id)->first();
        $this->assertNotNull($variant2);
        $this->assertSame('Variant 2', $variant2->variant_name);
        tenancy()->end();
    }

    public function test_attributes_json_storage(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);
        $suffix = uniqid();
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Electronics-' . $suffix, 'is_active' => true]);
        $brandId = DB::table('brands')->insertGetId(['name' => 'Samsung-' . $suffix, 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'Piece-' . $suffix, 'code' => 'PCS-' . $suffix, 'is_active' => true]);
        $productId = DB::table('products')->insertGetId([
            'code' => 'LAPTOP-' . $suffix,
            'name' => 'Laptop Pro',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'uom_id' => $uomId,
            'is_active' => true,
        ]);
        tenancy()->end();

        // Create variant with attributes
        $this->actingAs($user)->post(route('product.products.variants.store', ['product' => $productId]), [
            'product_id' => $productId,
            'sku' => 'PHONE-001-BLK-128',
            'variant_name' => 'Black / 128GB',
            'attributes' => ['color' => 'Black', 'storage' => '128GB', 'dual_sim' => true],
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $variant = DB::table('product_variants')->where('sku', 'PHONE-001-BLK-128')->first();
        $attributes = json_decode($variant->attributes, true);
        $this->assertSame('Black', $attributes['color']);
        $this->assertSame('128GB', $attributes['storage']);
        $this->assertTrue($attributes['dual_sim']);
        tenancy()->end();
    }

    private function createCompanyWithMember(): array
    {
        $id = uniqid('var_');
        $schemaName = 'sch_' . $id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Variant Test Corp',
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