<?php

namespace Modules\Product\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Product\Application\Variant\GetSaleVariants;
use Tests\TestCase;

class GetSaleVariantsTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();
        $this->cleanupCentralTables();
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

        parent::tearDown();
    }

    public function test_returns_only_active_sellable_variants_with_selling_price(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $sellableVariant = $this->createProductAndVariant($branchId, 'SALE-OK-'.uniqid(), true, true, 15000);
        $this->createProductAndVariant($branchId, 'SALE-NOT-SOLD-'.uniqid(), true, false, 15000);
        $this->createProductAndVariant($branchId, 'SALE-INACTIVE-'.uniqid(), false, true, 15000);

        $variants = app(GetSaleVariants::class)->execute([$branchId]);

        $this->assertCount(1, $variants);
        $this->assertSame($sellableVariant, $variants[0]['id']);
        $this->assertSame(15000.0, $variants[0]['selling_price']);
        $this->assertArrayHasKey('uom_name', $variants[0]);

        tenancy()->end();
    }

    public function test_branch_scope_filters_variants(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $otherBranchId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB-'.uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
        ]);

        $ownVariant = $this->createProductAndVariant($branchId, 'SALE-A-'.uniqid(), true, true, 10000);
        $this->createProductAndVariant((int) $otherBranchId, 'SALE-B-'.uniqid(), true, true, 20000);

        $variants = app(GetSaleVariants::class)->execute([$branchId]);

        $this->assertSame([$ownVariant], array_column($variants, 'id'));

        tenancy()->end();
    }

    private function createProductAndVariant(
        int $branchId,
        string $sku,
        bool $isActive,
        bool $isSold,
        float $sellingPrice,
    ): int {
        $categoryId = DB::table('product_categories')->insertGetId([
            'name' => 'Category '.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'Unit '.uniqid(),
            'code' => 'UOM-'.uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'branch_id' => $branchId,
            'code' => 'P-'.$sku,
            'name' => 'Product '.$sku,
            'category_id' => $categoryId,
            'uom_id' => $uomId,
            'product_type' => 'single',
            'is_purchased' => true,
            'is_sold' => $isSold,
            'selling_price' => $sellingPrice,
            'is_inventory_tracked' => true,
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => $sku,
            'variant_name' => 'Default',
            'is_active' => $isActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array{0: string|int, 1: int}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('gsv_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Sale Variants Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $existingHq = DB::table('branches')
            ->where('code', 'HQ')
            ->value('id');

        $branchId = $existingHq
            ? (int) $existingHq
            : DB::table('branches')->insertGetId([
                'name' => 'HQ Branch',
                'code' => 'HQ',
                'is_headquarters' => true,
                'is_active' => true,
            ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'member_'.$id.'@acme.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'member',
            'is_default' => true,
        ]);

        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $branchId,
        ]);

        return [$tenant->id, (int) $branchId];
    }

    private function cleanupCentralTables(): void
    {
        $this->deleteIfTableExists('company_user_branches');
        $this->deleteIfTableExists('company_users');
        $this->deleteIfTableExists('tenants');
        $this->deleteIfTableExists('users');
    }

    private function deleteIfTableExists(string $table): void
    {
        $exists = DB::selectOne(
            'SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = current_schema()
                AND table_name = ?
            ) AS exists',
            [$table]
        );

        if ($exists && (bool) $exists->exists) {
            DB::table($table)->delete();
        }
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            $safeSchemaName = str_replace('"', '""', $schemaName);

            DB::statement(
                'DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE'
            );
        } catch (\Throwable $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name
                 FROM information_schema.schemata
                 WHERE schema_name NOT IN (
                     'public',
                     'information_schema'
                 )
                 AND schema_name NOT LIKE 'pg_%'"
            );

            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;

                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
