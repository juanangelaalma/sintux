<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
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
        $suffix = uniqid();
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Electronics-'.$suffix, 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'Piece-'.$suffix, 'code' => 'PCS-'.$suffix, 'is_active' => true]);
        $productId = DB::table('products')->insertGetId([
            'code' => 'LAPTOP-'.$suffix,
            'name' => 'Laptop Pro',
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'is_active' => true,
        ]);
        tenancy()->end();

        // Create Variant
        $sku = 'LAPTOP-'.$suffix.'-BLK-16';
        $this->actingAs($user)->post(route('product.products.variants.store', ['product' => $productId]), [
            'product_id' => $productId,
            'sku' => $sku,
            'variant_name' => 'Black 16GB',
            'attributes' => ['color' => 'Black', 'ram' => '16GB'],
            'is_active' => true,
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $variant = DB::table('product_variants')->where('sku', $sku)->first();
        $this->assertNotNull($variant);
        $this->assertSame('Black 16GB', $variant->variant_name);
        $this->assertSame($productId, $variant->product_id);
        $variantId = $variant->id;
        tenancy()->end();

        // Update Variant
        $this->actingAs($user)->put(route('product.products.variants.update', ['product' => $productId, 'variant' => $variantId]), [
            'sku' => 'LAPTOP-'.$suffix.'-BLK-32',
            'variant_name' => 'Black 32GB',
            'attributes' => ['color' => 'Black', 'ram' => '32GB'],
            'is_active' => false,
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $variantUpdated = DB::table('product_variants')->where('id', $variantId)->first();
        $this->assertSame('Black 32GB', $variantUpdated->variant_name);
        $this->assertSame('LAPTOP-'.$suffix.'-BLK-32', $variantUpdated->sku);
        $this->assertFalse((bool) $variantUpdated->is_active);
        tenancy()->end();

        // Delete Variant
        $this->actingAs($user)->delete(route('product.products.variants.destroy', ['product' => $productId, 'variant' => $variantId]))
            ->assertRedirect();

        tenancy()->initialize($tenantId);
        $deletedVariant = DB::table('product_variants')->where('id', $variantId)->first();
        $this->assertNotNull($deletedVariant->deleted_at);
        tenancy()->end();
    }
        tenancy()->end();
    }

    private function createCompanyWithMember(): array
    {
        $id = uniqid('var_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Variant Test Corp',
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
