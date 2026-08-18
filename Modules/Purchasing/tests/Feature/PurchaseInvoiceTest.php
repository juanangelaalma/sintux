<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Models\PurchaseInvoice;
use Modules\Purchasing\Models\PurchaseOrder;
use Tests\TestCase;

class PurchaseInvoiceTest extends TestCase
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

    public function test_member_can_create_purchase_invoice(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $branchCode = DB::table('branches')->where('id', $branchBId)->value('code');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.invoices.store'), [
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'invoice_date' => '2026-08-18',
            'due_date' => '2026-09-18',
            'note' => 'Tagihan Pembelian',
            'items' => [
                ['product_variant_id' => $variantId, 'qty' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.invoices.index'));

        tenancy()->initialize($tenantId);
        $inv = DB::table('purchase_invoices')->where('branch_id', $branchBId)->first();
        $this->assertNotNull($inv);
        $this->assertSame('draft', $inv->status);
        $this->assertSame('INV-'.$branchCode.'-0001', (string) $inv->number);
        $this->assertEquals(500000, (float) $inv->subtotal);
        tenancy()->end();
    }

    public function test_approve_invoice_transitions_draft_to_approved(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $supplierId = $this->createSupplier($branchBId);

        $inv = PurchaseInvoice::create([
            'number' => 'INV-HQ-0001',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $invId = $inv->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.invoices.approve', $invId));
        $response->assertRedirect(route('purchasing.invoices.show', $invId));

        tenancy()->initialize($tenantId);
        $this->assertSame('approved', DB::table('purchase_invoices')->where('id', $invId)->value('status'));
        tenancy()->end();
    }

    public function test_3_way_match_validation_rejects_invoice_qty_exceeding_received_qty(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);

        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);

        $po = PurchaseOrder::create([
            'number' => 'PO-BRB-0010',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => 'sent',
            'order_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $poItem = $po->items()->create([
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_ordered' => 20,
            'qty_received' => 5, // Only 5 received
            'unit_price' => 50000,
        ]);

        $inv = PurchaseInvoice::create([
            'number' => 'INV-BRB-0010',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'purchase_order_id' => $po->id,
            'status' => 'draft',
            'invoice_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $inv->items()->create([
            'purchase_order_item_id' => $poItem->id,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty' => 10, // Trying to invoice 10 > 5 received!
            'unit_price' => 50000,
        ]);
        $invId = $inv->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.invoices.approve', $invId));
        $response->assertSessionHasErrors(['invoice']);

        tenancy()->initialize($tenantId);
        $this->assertSame('draft', DB::table('purchase_invoices')->where('id', $invId)->value('status'));
        tenancy()->end();
    }

    /**
     * @return array{0: string|int, 1: int, 2: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('inv_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Invoice Test-'.$id,
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
        $this->deleteIfTableExists('purchase_invoice_items');
        $this->deleteIfTableExists('purchase_invoices');
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
