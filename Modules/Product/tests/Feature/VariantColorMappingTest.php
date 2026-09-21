<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Product\Application\Variant\CreateVariant;
use Modules\Product\Application\Variant\EnsureMappedVariant;
use Modules\Product\Application\Variant\FindVariantBySkuAndColor;
use Modules\Product\Application\Variant\FindVariantSummary;
use Modules\Product\Application\Variant\UpdateVariant;
use Tests\TestCase;

class VariantColorMappingTest extends TestCase
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

    public function test_same_sku_with_different_colors_is_allowed(): void
    {
        [$tenantId, $branchId] = $this->createTenantWithBranch();
        tenancy()->initialize($tenantId);

        $productId = $this->createProduct($branchId, 'PAA-COLOR-001');

        DB::table('product_variants')->insert([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'PAA-COLOR-001',
            'variant_name' => 'Sarung - HITAM BIRU TOSCA',
            'attributes' => json_encode(['color' => 'HITAM BIRU TOSCA']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Warna berbeda, SKU sama → harus lolos (tidak throw).
        DB::table('product_variants')->insert([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'PAA-COLOR-001',
            'variant_name' => 'Sarung - ARMY HIJAU SAGE',
            'attributes' => json_encode(['color' => 'ARMY HIJAU SAGE']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(2, (int) DB::table('product_variants')
            ->where('branch_id', $branchId)
            ->where('sku', 'PAA-COLOR-001')
            ->count());

        tenancy()->end();
    }

    public function test_same_sku_with_same_color_is_rejected_by_database(): void
    {
        [$tenantId, $branchId] = $this->createTenantWithBranch();
        tenancy()->initialize($tenantId);

        $productId = $this->createProduct($branchId, 'PAA-COLOR-002');

        DB::table('product_variants')->insert([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'PAA-COLOR-002',
            'variant_name' => 'Sarung - HITAM',
            'attributes' => json_encode(['color' => 'HITAM']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('product_variants')->insert([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'PAA-COLOR-002',
            'variant_name' => 'Sarung - HITAM duplikat',
            'attributes' => json_encode(['color' => 'HITAM']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();
    }

    public function test_find_variant_by_sku_and_color_is_case_insensitive(): void
    {
        [$tenantId, $branchId] = $this->createTenantWithBranch();
        tenancy()->initialize($tenantId);

        $productId = $this->createProduct($branchId, 'PAA-COLOR-003');

        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'PAA-COLOR-003',
            'variant_name' => 'Sarung - NAVY BIRU PRUSI',
            'attributes' => json_encode(['color' => 'NAVY BIRU PRUSI']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $found = app(FindVariantBySkuAndColor::class)->execute(
            'PAA-COLOR-003', '  navy   biru prusi ', $branchId
        );

        $this->assertNotNull($found);
        $this->assertSame((int) $variantId, (int) $found->id);

        $this->assertNull(
            app(FindVariantBySkuAndColor::class)->execute('PAA-COLOR-003', 'MERAH', $branchId)
        );

        tenancy()->end();
    }

    public function test_ensure_mapped_variant_creates_master_and_is_idempotent(): void
    {
        [$tenantId, $branchId] = $this->createTenantWithBranch();
        tenancy()->initialize($tenantId);

        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Sarung', 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'PCS', 'code' => 'PCS-MAP', 'is_active' => true]);

        $payload = [
            'owner_branch_id' => $branchId,
            'sku' => 'PAA-MAP-001',
            'color' => 'HITAM EMAS KREM',
            'product_name' => 'SR PLK AS FIT SRI SKT 02 DX G',
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'purchase_price' => 5000000,
            'supplier_do_no' => 'DO/2608/00230',
        ];

        $first = app(EnsureMappedVariant::class)->execute($payload);
        $second = app(EnsureMappedVariant::class)->execute($payload);

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame('PAA-MAP-001', $first->sku);
        $this->assertSame('HITAM EMAS KREM', $first->attributes['color']);
        $this->assertSame(1, (int) DB::table('product_variants')
            ->where('branch_id', $branchId)
            ->where('sku', 'PAA-MAP-001')
            ->count());

        // Tanpa kategori/satuan (auto-mapping prd_code baru): produk dibuat
        // dengan null, dilengkapi manual di modul Produk.
        $auto = app(EnsureMappedVariant::class)->execute([
            'owner_branch_id' => $branchId,
            'sku' => 'PAA-MAP-002',
            'color' => 'NAVY BIRU PRUSI',
            'product_name' => 'SR PLK AS FIT SRI SKT 01 DX GM',
            'supplier_do_no' => 'DO/2608/00230',
        ]);

        $autoProduct = DB::table('products')->where('id', $auto->product_id)->first();
        $this->assertNull($autoProduct->category_id);
        $this->assertNull($autoProduct->uom_id);

        tenancy()->end();
    }

    public function test_mixed_case_duplicate_color_is_rejected_by_database(): void
    {
        [$tenantId, $branchId] = $this->createTenantWithBranch();
        tenancy()->initialize($tenantId);

        $productId = $this->createProduct($branchId, 'PAA-CASE-001');
        $row = [
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'PAA-CASE-001',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('product_variants')->insert($row + [
            'variant_name' => 'Sarung - hitam',
            'attributes' => json_encode(['color' => 'hitam']),
        ]);

        $this->expectException(QueryException::class);

        DB::table('product_variants')->insert($row + [
            'variant_name' => 'Sarung - HITAM',
            'attributes' => json_encode(['color' => 'HITAM']),
        ]);
    }

    public function test_create_variant_normalizes_color_on_write(): void
    {
        [$tenantId, $branchId] = $this->createTenantWithBranch();
        tenancy()->initialize($tenantId);

        $productId = $this->createProduct($branchId, 'PAA-NORM-001');

        $variant = app(CreateVariant::class)->execute([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'PAA-NORM-001',
            'variant_name' => 'Sarung - hitam muda',
            'attributes' => ['color' => '  hitam   muda ', 'size' => 'L'],
        ]);

        $stored = $variant->fresh()->attributes;
        $this->assertSame('HITAM MUDA', $stored['color']);
        $this->assertSame('L', $stored['size']);

        tenancy()->end();
    }

    public function test_update_variant_normalizes_color_on_write(): void
    {
        [$tenantId, $branchId] = $this->createTenantWithBranch();
        tenancy()->initialize($tenantId);

        $productId = $this->createProduct($branchId, 'PAA-NORM-002');

        $variant = app(CreateVariant::class)->execute([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'PAA-NORM-002',
            'variant_name' => 'Sarung - navy',
            'attributes' => ['color' => 'NAVY'],
        ]);

        app(UpdateVariant::class)->execute($variant->id, [
            'attributes' => ['color' => 'navy  biru prusi', 'size' => 'XL'],
        ]);

        $stored = $variant->fresh()->attributes;
        $this->assertSame('NAVY BIRU PRUSI', $stored['color']);
        $this->assertSame('XL', $stored['size']);

        tenancy()->end();
    }

    public function test_find_variant_summary_returns_id_and_uom_name(): void
    {
        [$tenantId, $branchId] = $this->createTenantWithBranch();
        tenancy()->initialize($tenantId);

        $productId = $this->createProduct($branchId, 'PAA-SUM-001');
        $uomName = (string) DB::table('uoms')
            ->whereIn('id', DB::table('products')->where('id', $productId)->select('uom_id'))
            ->value('name');

        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => 'PAA-SUM-001',
            'variant_name' => 'Sarung - NAVY BIRU PRUSI',
            'attributes' => json_encode(['color' => 'NAVY BIRU PRUSI']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $found = app(FindVariantSummary::class)->execute(
            'PAA-SUM-001', '  navy   biru prusi ', $branchId
        );

        $this->assertNotNull($found);
        $this->assertSame((int) $variantId, $found['id']);
        $this->assertSame($uomName, $found['uom_name']);

        $this->assertNull(
            app(FindVariantSummary::class)->execute('PAA-SUM-001', 'MERAH', $branchId)
        );

        tenancy()->end();
    }

    /**
     * @return array{0: string|int, 1: int}
     */
    private function createTenantWithBranch(): array
    {
        $id = uniqid('colormap_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Color Map Test '.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        tenancy()->initialize($tenant);
        $branchId = (int) DB::table('branches')->insertGetId([
            'name' => 'HQ',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();

        $user = User::factory()->create(['email' => 'member_'.$id.'@acme.test', 'role' => 'user']);
        CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'is_default' => true,
        ]);

        return [$tenant->id, $branchId];
    }

    private function createProduct(int $branchId, string $code): int
    {
        $categoryId = DB::table('product_categories')->insertGetId(['name' => 'Cat '.uniqid(), 'is_active' => true]);
        $uomId = DB::table('uoms')->insertGetId(['name' => 'Pcs '.uniqid(), 'code' => 'PCS'.uniqid(), 'is_active' => true]);

        return (int) DB::table('products')->insertGetId([
            'branch_id' => $branchId,
            'code' => $code,
            'name' => 'Product '.$code,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
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
                "SELECT schema_name FROM information_schema.schemata WHERE schema_name NOT IN ('public', 'information_schema') AND schema_name NOT LIKE 'pg_%'"
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
