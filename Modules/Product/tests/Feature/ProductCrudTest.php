<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Tests\Support\EligibleTaxFixture;
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

        // Setup: Create category and uom
        tenancy()->initialize($tenantId);
        $suffix = uniqid();
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Electronics-'.$suffix,
            'is_active' => true,
        ]);
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'Piece-'.$suffix,
            'code' => 'PCS-'.$suffix,
            'is_active' => true,
        ]);
        [$purchaseTaxId, $salesTaxId] = EligibleTaxFixture::create($suffix);
        tenancy()->end();

        $this->actingAs($user)->post(route('product.products.store'), [
            'code' => 'INVALID-TAX-'.$suffix,
            'name' => 'Invalid tax product',
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'purchase_tax_id' => $salesTaxId,
        ])->assertSessionHasErrors('purchase_tax_id');

        $this->actingAs($user)->post(route('product.products.store'), [
            'code' => 'INVALID-SALES-TAX-'.$suffix,
            'name' => 'Invalid sales tax product',
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'sales_tax_id' => $purchaseTaxId,
        ])->assertSessionHasErrors('sales_tax_id');

        $this->actingAs($user)
            ->get(route('product.products.create'))
            ->assertInertia(
                fn ($page) => $page
                    ->has('purchaseTaxes', fn ($taxes) => $taxes->where('id', $purchaseTaxId))
                    ->has('salesTaxes', fn ($taxes) => $taxes->where('id', $salesTaxId))
            );

        $this->actingAs($user)
            ->get(route('product.products.index'))
            ->assertStatus(200);

        // Create Product Single
        $codeLaptop = 'LAPTOP-'.$suffix;
        $this->actingAs($user)->post(route('product.products.store'), [
            'code' => $codeLaptop,
            'name' => 'Laptop Pro',
            'barcode' => '880123456789',
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'description' => 'High-end laptop',
            'product_type' => 'single',
            'is_purchased' => true,
            'purchase_price' => 10000000,
            'purchase_tax_id' => $purchaseTaxId,
            'is_sold' => true,
            'selling_price' => 15000000,
            'sales_tax_id' => $salesTaxId,
            'is_inventory_tracked' => true,
            'min_stock' => 5,
            'is_active' => true,
        ])->assertRedirect(route('product.products.index'));

        tenancy()->initialize($tenantId);
        $product = DB::table('products')->where('code', $codeLaptop)->first();
        $this->assertNotNull($product);
        $this->assertSame('Laptop Pro', $product->name);
        $this->assertSame($codeLaptop, $product->code);
        $this->assertSame('880123456789', $product->barcode);
        $this->assertSame('single', $product->product_type);
        $this->assertSame(15000000, (int) $product->selling_price);
        $this->assertSame(10000000, (int) $product->purchase_price);
        $this->assertSame($categoryId, $product->category_id);
        $this->assertSame($uomId, $product->uom_id);

        // Verify primary variant auto-created for 1:1 Warehouse compatibility
        $variant = DB::table('product_variants')->where('product_id', $product->id)->first();
        $this->assertNotNull($variant);
        $this->assertSame($codeLaptop, $variant->sku);
        tenancy()->end();

        // Update Product
        $this->actingAs($user)->put(route('product.products.update', ['product' => $product->id]), [
            'code' => 'LAPTOP-002',
            'name' => 'Laptop Pro Max',
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'description' => 'Updated description',
            'selling_price' => 18000000,
            'purchase_tax_id' => $salesTaxId,
            'is_active' => false,
        ])->assertSessionHasErrors('purchase_tax_id');

        $this->actingAs($user)->put(route('product.products.update', ['product' => $product->id]), [
            'code' => 'LAPTOP-002',
            'name' => 'Laptop Pro Max',
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'description' => 'Updated description',
            'selling_price' => 18000000,
            'is_active' => false,
        ])->assertRedirect(route('product.products.index'));

        tenancy()->initialize($tenantId);
        $productUpdated = DB::table('products')->where('id', $product->id)->first();
        $this->assertSame('Laptop Pro Max', $productUpdated->name);
        $this->assertSame('LAPTOP-002', $productUpdated->code);
        $this->assertSame(18000000, (int) $productUpdated->selling_price);
        $this->assertFalse((bool) $productUpdated->is_active);
        tenancy()->end();

        // Delete Product
        $this->actingAs($user)->delete(route('product.products.destroy', ['product' => $product->id]))
            ->assertRedirect();

        tenancy()->initialize($tenantId);
        $productDeleted = DB::table('products')->where('id', $product->id)->first();
        $this->assertNotNull($productDeleted->deleted_at);
        tenancy()->end();
    }

    public function test_can_create_bundle_product_with_items(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);

        tenancy()->initialize($tenantId);
        $suffix = uniqid();
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Bundle-'.$suffix, 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'Set-'.$suffix, 'code' => 'SET-'.$suffix, 'is_active' => true]);

        $item1Id = DB::table('products')->insertGetId([
            'code' => 'ITEM1-'.$suffix,
            'name' => 'Mouse',
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'selling_price' => 100000,
            'is_active' => true,
        ]);

        $item2Id = DB::table('products')->insertGetId([
            'code' => 'ITEM2-'.$suffix,
            'name' => 'Keyboard',
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'selling_price' => 200000,
            'is_active' => true,
        ]);
        tenancy()->end();

        // Create Bundle Product
        $bundleCode = 'BUNDLE-'.$suffix;
        $this->actingAs($user)->post(route('product.products.store'), [
            'code' => $bundleCode,
            'name' => 'Paket Gaming Mouse + Keyboard',
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'product_type' => 'bundle',
            'selling_price' => 280000,
            'bundle_items' => [
                ['item_product_id' => $item1Id, 'quantity' => 1],
                ['item_product_id' => $item2Id, 'quantity' => 1],
            ],
        ])->assertRedirect(route('product.products.index'));

        tenancy()->initialize($tenantId);
        $bundle = DB::table('products')->where('code', $bundleCode)->first();
        $this->assertNotNull($bundle);
        $this->assertSame('bundle', $bundle->product_type);

        $bundleItems = DB::table('product_bundle_items')->where('bundle_product_id', $bundle->id)->get();
        $this->assertCount(2, $bundleItems);
        tenancy()->end();
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
