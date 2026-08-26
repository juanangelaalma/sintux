<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Approval\Application\CreateApprovalRule;
use Modules\Approval\Models\ApprovalTransactionType;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Models\PurchaseOrder;
use Tests\TestCase;

class PurchaseOrderTest extends TestCase
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

    public function test_member_can_create_purchase_order(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $branchCode = DB::table('branches')->where('id', $branchBId)->value('code');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-18',
            'expected_date' => '2026-08-25',
            'note' => 'Pesanan Pembelian Resmi',
            'items' => [
                ['product_variant_id' => $variantId, 'qty_ordered' => 20, 'unit_price' => 100000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.orders.index'));

        tenancy()->initialize($tenantId);
        $po = DB::table('purchase_orders')->where('branch_id', $branchBId)->first();
        $this->assertNotNull($po);
        $this->assertSame('approved', $po->status);
        $expectedPrefix = 'PO/'.$branchCode.'/'.date('Y/m/d').'/000';
        $this->assertStringStartsWith('PO/'.$branchCode.'/', (string) $po->number);
        $this->assertEquals(2000000, (float) $po->subtotal);

        $items = DB::table('purchase_order_items')->where('purchase_order_id', $po->id)->get();
        $this->assertCount(1, $items);
        $this->assertEquals(20, (float) $items->first()->qty_ordered);
        $this->assertEquals(0, (float) $items->first()->qty_received);
        $this->assertEquals(100000, (float) $items->first()->unit_price);
        tenancy()->end();
    }

    public function test_po_with_matching_rule_transitions_to_pending(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $branchCode = DB::table('branches')->where('id', $branchBId)->value('code');

        $type = ApprovalTransactionType::where('key', 'purchase_order')->firstOrFail();
        app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $type->id,
            'name' => 'PO Rule',
            'min_amount' => 100000,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$user->id + 999]], // different user as approver
            ],
        ], $user->id);

        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.store'), [
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'order_date' => '2026-08-18',
            'expected_date' => '2026-08-25',
            'items' => [
                ['product_variant_id' => $variantId, 'qty_ordered' => 20, 'unit_price' => 100000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.orders.index'));

        tenancy()->initialize($tenantId);
        $po = DB::table('purchase_orders')->where('branch_id', $branchBId)->first();
        $this->assertNotNull($po);
        $this->assertSame('pending', $po->status);
        tenancy()->end();
    }

    public function test_send_po_transitions_approved_to_sent(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $supplierId = $this->createSupplier($branchBId);

        $po = PurchaseOrder::create([
            'number' => 'PO-HQ-0002',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $poId = $po->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.send', $poId));
        $response->assertRedirect(route('purchasing.orders.show', $poId));

        tenancy()->initialize($tenantId);
        $this->assertSame('sent', DB::table('purchase_orders')->where('id', $poId)->value('status'));
        tenancy()->end();
    }

    public function test_cancel_po_transitions_to_cancelled(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $supplierId = $this->createSupplier($branchBId);

        $po = PurchaseOrder::create([
            'number' => 'PO-HQ-0003',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => 'pending',
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $poId = $po->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.orders.cancel', $poId));
        $response->assertRedirect(route('purchasing.orders.show', $poId));

        tenancy()->initialize($tenantId);
        $this->assertSame('cancelled', DB::table('purchase_orders')->where('id', $poId)->value('status'));
        tenancy()->end();
    }

    /**
     * @return array{0: string|int, 1: int, 2: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('po_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'PO Test-'.$id,
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
        $this->deleteIfTableExists('purchase_order_items');
        $this->deleteIfTableExists('purchase_orders');
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
