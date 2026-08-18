<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Models\PurchaseRequest;
use Tests\TestCase;

class PurchaseRequestTest extends TestCase
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

    public function test_member_can_create_purchase_request(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $branchCode = DB::table('branches')->where('id', $branchBId)->value('code');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.requests.store'), [
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'request_date' => '2026-08-18',
            'expected_date' => '2026-08-25',
            'note' => 'Restock bulanan',
            'items' => [
                ['product_variant_id' => $variantId, 'qty_requested' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.requests.index'));

        tenancy()->initialize($tenantId);
        $request = DB::table('purchase_requests')->where('branch_id', $branchBId)->first();
        $this->assertNotNull($request);
        $this->assertSame('pending', $request->status);
        $this->assertStringStartsWith('PR-'.$branchCode.'-', (string) $request->number);
        $this->assertSame('2026-08-18', (string) $request->request_date);
        $this->assertSame('Restock bulanan', $request->note);

        $items = DB::table('purchase_request_items')->where('purchase_request_id', $request->id)->get();
        $this->assertCount(1, $items);
        $this->assertEquals(10, (float) $items->first()->qty_requested);
        $this->assertEquals(50000, (float) $items->first()->unit_price);
        $this->assertNotEmpty($items->first()->product_name);
        $this->assertNotEmpty($items->first()->sku);
        tenancy()->end();
    }

    public function test_pr_number_is_sequential_per_branch(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $branchCode = DB::table('branches')->where('id', $branchBId)->value('code');
        tenancy()->end();

        $payload = [
            'branch_id' => $branchBId,
            'request_date' => '2026-08-18',
            'items' => [['product_variant_id' => $variantId, 'qty_requested' => 5]],
        ];

        $this->actingAs($user)->post(route('purchasing.requests.store'), $payload);
        $this->actingAs($user)->post(route('purchasing.requests.store'), $payload);

        tenancy()->initialize($tenantId);
        $numbers = DB::table('purchase_requests')
            ->where('branch_id', $branchBId)
            ->orderBy('id')
            ->pluck('number')
            ->all();
        $this->assertSame(
            ['PR-'.$branchCode.'-0001', 'PR-'.$branchCode.'-0002'],
            $numbers,
        );
        tenancy()->end();
    }

    public function test_validation_rejects_qty_less_than_or_equal_zero(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.requests.store'), [
            'branch_id' => $branchBId,
            'request_date' => '2026-08-18',
            'items' => [['product_variant_id' => $variantId, 'qty_requested' => 0]],
        ]);

        $response->assertSessionHasErrors(['items.0.qty_requested']);
    }

    public function test_validation_rejects_duplicate_variant_items(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.requests.store'), [
            'branch_id' => $branchBId,
            'request_date' => '2026-08-18',
            'items' => [
                ['product_variant_id' => $variantId, 'qty_requested' => 5],
                ['product_variant_id' => $variantId, 'qty_requested' => 10],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.product_variant_id', 'items.1.product_variant_id']);
    }

    public function test_validation_rejects_inactive_product_variant(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$inactiveVariantId] = $this->createProductAndVariant('PRD-INACTIVE', false);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.requests.store'), [
            'branch_id' => $branchBId,
            'request_date' => '2026-08-18',
            'items' => [['product_variant_id' => $inactiveVariantId, 'qty_requested' => 5]],
        ]);

        $response->assertSessionHasErrors(['items.0.product_variant_id']);
    }

    public function test_validation_rejects_branch_outside_accessible_scope(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $outOfScopeBranchId = DB::table('branches')->insertGetId([
            'name' => 'Branch X',
            'code' => 'BRX-'.uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.requests.store'), [
            'branch_id' => $outOfScopeBranchId,
            'request_date' => '2026-08-18',
            'items' => [['product_variant_id' => $variantId, 'qty_requested' => 5]],
        ]);

        $response->assertSessionHasErrors(['branch_id']);
    }

    public function test_approve_transitions_pending_to_approved(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $request = PurchaseRequest::create([
            'number' => 'PR-HQ-0001',
            'branch_id' => $branchBId,
            'status' => 'pending',
            'request_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $requestId = $request->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.requests.approve', $requestId));

        $response->assertRedirect(route('purchasing.requests.show', $requestId));

        tenancy()->initialize($tenantId);
        $this->assertSame('approved', DB::table('purchase_requests')->where('id', $requestId)->value('status'));
        tenancy()->end();
    }

    public function test_approve_rejects_non_pending_request(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $request = PurchaseRequest::create([
            'number' => 'PR-HQ-0002',
            'branch_id' => $branchBId,
            'status' => 'cancelled',
            'request_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $requestId = $request->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.requests.approve', $requestId));

        $response->assertSessionHasErrors('request');

        tenancy()->initialize($tenantId);
        $this->assertSame('cancelled', DB::table('purchase_requests')->where('id', $requestId)->value('status'));
        tenancy()->end();
    }

    public function test_cancel_transitions_pending_to_cancelled(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $request = PurchaseRequest::create([
            'number' => 'PR-HQ-0003',
            'branch_id' => $branchBId,
            'status' => 'pending',
            'request_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $requestId = $request->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.requests.cancel', $requestId));

        $response->assertRedirect(route('purchasing.requests.index'));

        tenancy()->initialize($tenantId);
        $this->assertSame('cancelled', DB::table('purchase_requests')->where('id', $requestId)->value('status'));
        tenancy()->end();
    }

    public function test_non_member_cannot_create_purchase_request(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        tenancy()->end();

        $outsider = User::factory()->create([
            'email' => 'outsider_'.uniqid().'@acme.test',
            'role' => 'user',
        ]);

        $response = $this->actingAs($outsider)->post(route('purchasing.requests.store'), [
            'branch_id' => $branchBId,
            'request_date' => '2026-08-18',
            'items' => [['product_variant_id' => $variantId, 'qty_requested' => 5]],
        ]);

        $response->assertForbidden();
    }

    /**
     * @return array{0: string|int, 1: int, 2: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('pur_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Purchasing PR Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $existingHq = DB::table('branches')->where('code', 'HQ')->value('id');
        $hqBranchId = $existingHq
            ? (int) $existingHq
            : DB::table('branches')->insertGetId([
                'name' => 'HQ Branch',
                'code' => 'HQ',
                'is_headquarters' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_'.uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
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
            'branch_id' => $hqBranchId,
        ]);
        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $branchBId,
        ]);

        return [$tenant->id, (int) $branchBId, $user];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function createProductAndVariant(string $codePrefix = 'PRD', bool $variantActive = true): array
    {
        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Category '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS '.uniqid(),
            'code' => 'PCS'.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'code' => $codePrefix.'-'.uniqid(),
            'name' => 'Widget '.uniqid(),
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'SKU-'.uniqid(),
            'variant_name' => 'Widget Variant '.uniqid(),
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => $variantActive,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$variantId, $productId];
    }

    private function createSupplier(int $branchId): int
    {
        return (int) DB::table('contacts')->insertGetId([
            'branch_id' => $branchId,
            'type' => 'supplier',
            'name' => 'Supplier '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function cleanupCentralTables(): void
    {
        $this->deleteIfTableExists('purchase_request_items');
        $this->deleteIfTableExists('purchase_requests');
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
            DB::statement('DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE');
        } catch (\Throwable $e) {
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name
                 FROM information_schema.schemata
                 WHERE schema_name NOT IN ('public', 'information_schema')
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
