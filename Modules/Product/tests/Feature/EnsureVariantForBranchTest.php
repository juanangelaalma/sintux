<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Product\Application\Product\EnsureVariantForBranch;
use Tests\TestCase;

class EnsureVariantForBranchTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();
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

    /**
     * Missing master: mirror product + variant are created for the branch.
     */
    public function test_creates_mirror_product_and_variant_when_missing(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $sourceVariantId, $sourceProductId] =
            $this->seedTenantWithBranchesAndProduct();

        tenancy()->initialize($tenantId);

        $resolvedId = app(EnsureVariantForBranch::class)
            ->execute($sourceVariantId, $branchBId);

        $this->assertNotSame($sourceVariantId, $resolvedId);

        $mirrorVariant = DB::table('product_variants')
            ->where('id', $resolvedId)
            ->first();

        $this->assertNotNull($mirrorVariant);
        $this->assertSame($branchBId, (int) $mirrorVariant->branch_id);

        $sourceVariant = DB::table('product_variants')
            ->where('id', $sourceVariantId)
            ->first();
        $this->assertSame($sourceVariant->sku, $mirrorVariant->sku);

        $mirrorProduct = DB::table('products')
            ->where('id', $mirrorVariant->product_id)
            ->first();

        $this->assertNotNull($mirrorProduct);
        $this->assertSame($branchBId, (int) $mirrorProduct->branch_id);
        $this->assertNotSame($sourceProductId, (int) $mirrorProduct->id);

        $sourceProduct = DB::table('products')
            ->where('id', $sourceProductId)
            ->first();
        $this->assertSame($sourceProduct->code, $mirrorProduct->code);
        $this->assertSame($sourceProduct->name, $mirrorProduct->name);
        $this->assertSame($sourceProduct->uom_id, $mirrorProduct->uom_id);
        $this->assertSame($sourceProduct->category_id, $mirrorProduct->category_id);

        tenancy()->end();
    }

    /**
     * Existing master: the branch variant is reused, no duplicates.
     */
    public function test_reuses_existing_branch_master(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $sourceVariantId, $sourceProductId] =
            $this->seedTenantWithBranchesAndProduct();

        tenancy()->initialize($tenantId);

        $first = app(EnsureVariantForBranch::class)
            ->execute($sourceVariantId, $branchBId);
        $second = app(EnsureVariantForBranch::class)
            ->execute($sourceVariantId, $branchBId);

        $this->assertSame($first, $second);

        $sourceVariant = DB::table('product_variants')
            ->where('id', $sourceVariantId)
            ->first();

        $this->assertSame(1, DB::table('products')
            ->where('branch_id', $branchBId)
            ->where('code', DB::table('products')->where('id', $sourceProductId)->value('code'))
            ->count());

        $this->assertSame(1, DB::table('product_variants')
            ->where('branch_id', $branchBId)
            ->where('sku', $sourceVariant->sku)
            ->count());

        tenancy()->end();
    }

    /**
     * Same branch: no-op, returns the source variant id unchanged.
     */
    public function test_returns_source_variant_for_same_branch(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $sourceVariantId] =
            $this->seedTenantWithBranchesAndProduct();

        tenancy()->initialize($tenantId);

        $resolvedId = app(EnsureVariantForBranch::class)
            ->execute($sourceVariantId, $hqBranchId);

        $this->assertSame($sourceVariantId, $resolvedId);

        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: int, 4: int}
     */
    private function seedTenantWithBranchesAndProduct(): array
    {
        $id = uniqid('evf_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Ensure Variant Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ_EVF_'.$id,
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_EVF_'.$id,
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $suffix = uniqid();

        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Category '.$suffix,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS '.$suffix,
            'code' => 'PCS'.$suffix,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sourceProductId = DB::table('products')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'PRD-EVF-'.$suffix,
            'name' => 'Widget '.$suffix,
            'category_id' => $catId,
            'uom_id' => $uomId,
            'selling_price' => 15000,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sourceVariantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $hqBranchId,
            'product_id' => $sourceProductId,
            'sku' => 'SKU-EVF-'.$suffix,
            'variant_name' => 'Widget Variant '.$suffix,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        return [
            $tenant->id,
            (int) $hqBranchId,
            (int) $branchBId,
            (int) $sourceVariantId,
            (int) $sourceProductId,
        ];
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
                "SELECT schema_name FROM information_schema.schemata
                 WHERE schema_name NOT IN ('public', 'information_schema')
                 AND schema_name NOT LIKE 'pg_%'"
            );

            foreach ($schemas as $row) {
                if (str_starts_with($row->schema_name, 'sch_')) {
                    $this->dropSchema($row->schema_name);
                }
            }
        } catch (\Exception $e) {
        }
    }
}
