<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Models\PurchaseQuote;
use Modules\Purchasing\Models\PurchaseRequest;
use Tests\TestCase;

class PurchaseQuoteTest extends TestCase
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

    public function test_member_can_create_purchase_quote(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        $branchCode = DB::table('branches')->where('id', $branchBId)->value('code');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.quotes.store'), [
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'quote_date' => '2026-08-18',
            'valid_until' => '2026-09-18',
            'note' => 'Penawaran harga terbaik',
            'items' => [
                ['product_variant_id' => $variantId, 'qty' => 10, 'unit_price' => 50000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.quotes.index'));

        tenancy()->initialize($tenantId);
        $quote = DB::table('purchase_quotes')->where('branch_id', $branchBId)->first();
        $this->assertNotNull($quote);
        $this->assertSame('draft', $quote->status);
        $this->assertSame('QUOTE-'.$branchCode.'-0001', (string) $quote->number);
        $this->assertEquals(500000, (float) $quote->subtotal);

        $items = DB::table('purchase_quote_items')->where('purchase_quote_id', $quote->id)->get();
        $this->assertCount(1, $items);
        $this->assertEquals(10, (float) $items->first()->qty);
        $this->assertEquals(50000, (float) $items->first()->unit_price);
        $this->assertEquals(500000, (float) $items->first()->line_total);
        tenancy()->end();
    }

    public function test_member_can_create_quote_prefilled_from_purchase_request(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);

        $pr = PurchaseRequest::create([
            'number' => 'PR-HQ-0001',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => 'approved',
            'request_date' => '2026-08-18',
            'currency_code' => 'IDR',
        ]);
        $pr->items()->create([
            'product_variant_id' => $variantId,
            'product_name' => 'Widget PRD',
            'sku' => 'SKU-PRD',
            'qty_requested' => 15,
        ]);
        $prId = $pr->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.quotes.store'), [
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'source_request_id' => $prId,
            'quote_date' => '2026-08-18',
            'items' => [
                ['product_variant_id' => $variantId, 'qty' => 15, 'unit_price' => 45000],
            ],
        ]);

        $response->assertRedirect(route('purchasing.quotes.index'));

        tenancy()->initialize($tenantId);
        $quote = DB::table('purchase_quotes')->where('source_request_id', $prId)->first();
        $this->assertNotNull($quote);
        $this->assertEquals($prId, $quote->source_request_id);
        tenancy()->end();
    }

    public function test_send_quote_transitions_draft_to_sent(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $supplierId = $this->createSupplier($branchBId);

        $quote = PurchaseQuote::create([
            'number' => 'QUOTE-HQ-0001',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => 'draft',
            'quote_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $quoteId = $quote->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.quotes.send', $quoteId));
        $response->assertRedirect(route('purchasing.quotes.show', $quoteId));

        tenancy()->initialize($tenantId);
        $this->assertSame('sent', DB::table('purchase_quotes')->where('id', $quoteId)->value('status'));
        tenancy()->end();
    }

    public function test_accept_quote_transitions_sent_to_accepted(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $supplierId = $this->createSupplier($branchBId);

        $quote = PurchaseQuote::create([
            'number' => 'QUOTE-HQ-0002',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => 'sent',
            'quote_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $quoteId = $quote->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.quotes.accept', $quoteId));
        $response->assertRedirect(route('purchasing.quotes.show', $quoteId));

        tenancy()->initialize($tenantId);
        $this->assertSame('accepted', DB::table('purchase_quotes')->where('id', $quoteId)->value('status'));
        tenancy()->end();
    }

    public function test_cancel_quote_transitions_draft_or_sent_to_cancelled(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        $supplierId = $this->createSupplier($branchBId);

        $quote = PurchaseQuote::create([
            'number' => 'QUOTE-HQ-0003',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => 'draft',
            'quote_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);
        $quoteId = $quote->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.quotes.cancel', $quoteId));
        $response->assertRedirect(route('purchasing.quotes.index'));

        tenancy()->initialize($tenantId);
        $this->assertSame('cancelled', DB::table('purchase_quotes')->where('id', $quoteId)->value('status'));
        tenancy()->end();
    }

    public function test_validation_rejects_unit_price_less_than_zero(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);
        tenancy()->initialize($tenantId);
        [$variantId] = $this->createProductAndVariant('PRD-001', true);
        $supplierId = $this->createSupplier($branchBId);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.quotes.store'), [
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'quote_date' => '2026-08-18',
            'items' => [
                ['product_variant_id' => $variantId, 'qty' => 10, 'unit_price' => -100],
            ],
        ]);

        $response->assertSessionHasErrors(['items.0.unit_price']);
    }

    /**
     * @return array{0: string|int, 1: int, 2: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('quote_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Purchase Quote Test-'.$id,
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
        $this->deleteIfTableExists('purchase_quote_items');
        $this->deleteIfTableExists('purchase_quotes');
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
