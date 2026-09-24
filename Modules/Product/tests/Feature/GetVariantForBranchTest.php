<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Product\Application\Variant\GetVariantForBranch;
use Tests\TestCase;

class GetVariantForBranchTest extends TestCase
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

    public function test_same_branch_returns_variant_as_is(): void
    {
        [$tenantId, $hqBranchId, $hqVariantId] = $this->seedTenant();

        tenancy()->initialize($tenantId);

        $this->assertSame(
            ['variant_id' => $hqVariantId],
            app(GetVariantForBranch::class)->execute($hqVariantId, $hqBranchId)
        );

        tenancy()->end();
    }

    public function test_cross_branch_resolves_mirror_by_sku_and_color(): void
    {
        [$tenantId, $hqBranchId, $hqVariantId, $branchBId, $mirrorVariantId] = $this->seedTenantWithMirror();

        tenancy()->initialize($tenantId);

        // Varian cabang B di-resolve ke cermin HO (id berbeda, SKU+warna sama).
        $this->assertSame(
            ['variant_id' => $hqVariantId],
            app(GetVariantForBranch::class)->execute($mirrorVariantId, $hqBranchId)
        );

        tenancy()->end();
    }

    public function test_unknown_variant_or_missing_mirror_returns_null(): void
    {
        [$tenantId, $hqBranchId, $hqVariantId, $branchBId] = $this->seedTenant();

        tenancy()->initialize($tenantId);

        $this->assertNull(app(GetVariantForBranch::class)->execute(999999, $hqBranchId));
        // Varian HO tak punya padanan di cabang B.
        $this->assertNull(app(GetVariantForBranch::class)->execute($hqVariantId, $branchBId));

        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: int, 2: int}
     */
    private function seedTenant(): array
    {
        $id = uniqid('gvb_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Variant Branch Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ_GVB_'.$id,
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_GVB_'.$id,
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $hqVariantId = $this->createVariant($hqBranchId, 'SKU-GVB-'.$id);

        tenancy()->end();

        return [$tenant->id, (int) $hqBranchId, (int) $hqVariantId, (int) $branchBId];
    }

    /**
     * @return array{0: string, 1: int, 2: int, 3: int, 4: int}
     */
    private function seedTenantWithMirror(): array
    {
        [$tenantId, $hqBranchId, $hqVariantId, $branchBId] = $this->seedTenant();

        tenancy()->initialize($tenantId);

        $suffix = explode('SKU-GVB-', DB::table('product_variants')->where('id', $hqVariantId)->value('sku'))[1];
        $mirrorVariantId = $this->createVariant($branchBId, 'SKU-GVB-'.$suffix);

        tenancy()->end();

        return [$tenantId, (int) $hqBranchId, (int) $hqVariantId, (int) $branchBId, (int) $mirrorVariantId];
    }

    private function createVariant(int $branchId, string $sku): int
    {
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
        $productId = DB::table('products')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'PRD-'.$suffix,
            'name' => 'Widget '.$suffix,
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => $sku,
            'variant_name' => 'Widget '.$suffix,
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
