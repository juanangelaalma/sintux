<?php

namespace Modules\Sales\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Accounting\Tests\Support\EligibleTaxFixture;
use Modules\Approval\Application\CreateApprovalRule;
use Modules\Approval\Application\PerformApprovalAction;
use Modules\Approval\Models\ApprovalMapping;
use Modules\Approval\Models\ApprovalTransactionType;
use Modules\Company\Models\CompanyUser;
use Modules\Sales\Enums\SalesInvoiceStatus;
use Modules\Sales\Events\SalesInvoiceApproved;
use Modules\Warehouse\Application\StockLayer\ReceivePurchaseStock;
use Modules\Warehouse\Application\Warehouse\CreateWarehousesForBranch;
use Modules\Warehouse\Models\StockBalance;
use Modules\Warehouse\Models\StockMovement;
use Modules\Warehouse\Models\StockReservation;
use Tests\TestCase;

class SalesInvoiceTest extends TestCase
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

    public function test_member_can_create_sales_invoice_auto_approved_and_consumes_stock(): void
    {
        Event::fake([SalesInvoiceApproved::class]);

        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranch();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        tenancy()->initialize($tenantId);
        [$variant1, $variant2, $salesTaxId] = $this->createCatalog($branchBId);
        $customerId = $this->createContact($branchBId, 'customer', 'buyer@acme.test');
        $employeeId = $this->createContact($branchBId, 'employee');
        $warehouseId = $this->regularWarehouseOf($branchBId);
        $branchCode = DB::table('branches')->where('id', $branchBId)->value('code');
        $this->receiveStock($warehouseId, $variant1, 25);
        $this->receiveStock($warehouseId, $variant2, 25);
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('sales.invoices.store'), $this->invoicePayload(
            $warehouseId, $customerId, $employeeId, $variant1, $variant2, $salesTaxId
        ));

        $response->assertRedirect(route('sales.invoices.index'));

        Event::assertDispatched(SalesInvoiceApproved::class);

        tenancy()->initialize($tenantId);
        $inv = DB::table('sales_invoices')->where('branch_id', $branchBId)->first();
        $this->assertNotNull($inv);
        $this->assertSame(SalesInvoiceStatus::Approved->value, $inv->status);
        $this->assertSame(sprintf('SI/%s/20260910/001', $branchCode), (string) $inv->number);
        $this->assertEquals(250000, (float) $inv->subtotal);
        $this->assertEquals(20000, (float) $inv->line_discount_total);
        $this->assertEquals(11500, (float) $inv->invoice_discount_amount);
        $this->assertEquals(26220, (float) $inv->tax_amount);
        $this->assertEquals(244720, (float) $inv->total);
        $this->assertSame('buyer@acme.test', (string) $inv->customer_email);

        $items = DB::table('sales_invoice_items')->where('sales_invoice_id', $inv->id)->orderBy('id')->get();
        $this->assertCount(2, $items);
        // Baris 1: 10 x 15000 -10% = 135000; alokasi disc invoice 6750; pajak 12% = 15390.
        $this->assertEquals(150000, (float) $items[0]->line_gross);
        $this->assertEquals(15000, (float) $items[0]->discount_amount);
        $this->assertEquals(135000, (float) $items[0]->line_net);
        $this->assertEquals(15390, (float) $items[0]->tax_amount);
        $this->assertEquals(143640, (float) $items[0]->line_total);
        // Baris 2: 5 x 20000 -5000 = 95000; alokasi disc invoice 4750; pajak 12% = 10830.
        $this->assertEquals(100000, (float) $items[1]->line_gross);
        $this->assertEquals(5000, (float) $items[1]->discount_amount);
        $this->assertEquals(95000, (float) $items[1]->line_net);
        $this->assertEquals(10830, (float) $items[1]->tax_amount);
        $this->assertEquals(101080, (float) $items[1]->line_total);

        // Stok fisik berkurang, reservasi habis, movement tercatat.
        $this->assertSame(15, (int) $this->balanceOf($warehouseId, $variant1));
        $this->assertSame(20, (int) $this->balanceOf($warehouseId, $variant2));
        $this->assertSame(0, StockReservation::query()->where('sales_invoice_id', $inv->id)->count());
        $this->assertSame(2, StockMovement::query()
            ->where('movement_type', 'sales_out')
            ->where('reference_id', $inv->id)
            ->count());
        tenancy()->end();
    }

    public function test_pending_approve_and_reject_flows(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranch();
        $approver = User::factory()->create(['email' => 'approver_si_'.$tenantId.'@acme.test', 'role' => 'user']);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        tenancy()->initialize($tenantId);
        [$variant1, $variant2, $salesTaxId] = $this->createCatalog($branchBId);
        $customerId = $this->createContact($branchBId, 'customer');
        $employeeId = $this->createContact($branchBId, 'employee');
        $warehouseId = $this->regularWarehouseOf($branchBId);
        $this->receiveStock($warehouseId, $variant1, 25);
        $this->receiveStock($warehouseId, $variant2, 25);

        $type = ApprovalTransactionType::where('key', 'sales_invoice')->firstOrFail();
        app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $type->id,
            'name' => 'SI Rule',
            'min_amount' => 100000,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$approver->id]],
            ],
        ], $user->id);
        tenancy()->end();

        $payload = $this->invoicePayload($warehouseId, $customerId, $employeeId, $variant1, $variant2, $salesTaxId);

        $this->actingAs($user)->post(route('sales.invoices.store'), $payload)
            ->assertRedirect(route('sales.invoices.index'));

        tenancy()->initialize($tenantId);
        $inv = DB::table('sales_invoices')->where('branch_id', $branchBId)->first();
        $this->assertSame(SalesInvoiceStatus::Pending->value, $inv->status);
        // Pending: reservasi ada, fisik utuh.
        $this->assertSame(2, StockReservation::query()->where('sales_invoice_id', $inv->id)->count());
        $this->assertSame(25, (int) $this->balanceOf($warehouseId, $variant1));

        $mapping = ApprovalMapping::where('transaction_type', 'sales_invoice')
            ->where('transaction_id', $inv->id)
            ->firstOrFail();
        app(PerformApprovalAction::class)->execute($mapping->id, $approver->id, 'approve');

        $this->assertSame(
            SalesInvoiceStatus::Approved->value,
            DB::table('sales_invoices')->where('id', $inv->id)->value('status')
        );
        $this->assertSame(15, (int) $this->balanceOf($warehouseId, $variant1));
        $this->assertSame(0, StockReservation::query()->where('sales_invoice_id', $inv->id)->count());
        tenancy()->end();

        // Faktur kedua → pending dengan nomor urut 002 → reject → cancelled + release.
        $this->actingAs($user)->post(route('sales.invoices.store'), $payload)
            ->assertRedirect(route('sales.invoices.index'));

        tenancy()->initialize($tenantId);
        $second = DB::table('sales_invoices')->where('branch_id', $branchBId)->orderByDesc('id')->first();
        $this->assertStringEndsWith('/002', (string) $second->number);
        $this->assertSame(SalesInvoiceStatus::Pending->value, $second->status);

        $mapping2 = ApprovalMapping::where('transaction_type', 'sales_invoice')
            ->where('transaction_id', $second->id)
            ->firstOrFail();
        app(PerformApprovalAction::class)->execute($mapping2->id, $approver->id, 'reject', 'Harga kemahalan');

        $this->assertSame(
            SalesInvoiceStatus::Cancelled->value,
            DB::table('sales_invoices')->where('id', $second->id)->value('status')
        );
        $this->assertSame(0, StockReservation::query()->where('sales_invoice_id', $second->id)->count());
        // Fisik tidak bergerak oleh faktur yang dibatalkan.
        $this->assertSame(15, (int) $this->balanceOf($warehouseId, $variant1));
        $this->assertSame(20, (int) $this->balanceOf($warehouseId, $variant2));
        tenancy()->end();
    }

    public function test_create_page_renders_with_sale_options(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranch();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        tenancy()->initialize($tenantId);
        [$variant1] = $this->createCatalog($branchBId);
        $this->createContact($branchBId, 'customer');
        $this->createContact($branchBId, 'employee');
        tenancy()->end();

        $this->actingAs($user)->get(route('sales.invoices.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Invoices/create')
                ->has('warehouses')
                ->has('customers')
                ->has('employees')
                ->has('productVariants')
                ->has('taxes')
                ->has('paymentTerms')
                ->where('productVariants.0.id', $variant1));
    }

    public function test_index_and_show_pages_render(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranch();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        tenancy()->initialize($tenantId);
        [$variant1, $variant2, $salesTaxId] = $this->createCatalog($branchBId);
        $customerId = $this->createContact($branchBId, 'customer');
        $employeeId = $this->createContact($branchBId, 'employee');
        $warehouseId = $this->regularWarehouseOf($branchBId);
        $this->receiveStock($warehouseId, $variant1, 25);
        $this->receiveStock($warehouseId, $variant2, 25);
        tenancy()->end();

        $payload = $this->invoicePayload($warehouseId, $customerId, $employeeId, $variant1, $variant2, $salesTaxId);

        $this->actingAs($user)->post(route('sales.invoices.store'), $payload)
            ->assertRedirect(route('sales.invoices.index'));

        $this->actingAs($user)->get(route('sales.invoices.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Invoices/index')
                ->has('salesInvoices.data', 1));

        tenancy()->initialize($tenantId);
        $invoiceId = (int) DB::table('sales_invoices')->value('id');
        tenancy()->end();

        $this->actingAs($user)->get(route('sales.invoices.show', $invoiceId))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sales/Invoices/show')
                ->where('salesInvoice.id', $invoiceId)
                ->has('salesInvoice.items', 2));
    }

    public function test_insufficient_stock_is_rejected_per_line(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranch();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        tenancy()->initialize($tenantId);
        [$variant1, $variant2, $salesTaxId] = $this->createCatalog($branchBId);
        $customerId = $this->createContact($branchBId, 'customer');
        $employeeId = $this->createContact($branchBId, 'employee');
        $warehouseId = $this->regularWarehouseOf($branchBId);
        $this->receiveStock($warehouseId, $variant1, 5);
        $this->receiveStock($warehouseId, $variant2, 25);
        tenancy()->end();

        $payload = $this->invoicePayload($warehouseId, $customerId, $employeeId, $variant1, $variant2, $salesTaxId);

        $this->actingAs($user)->post(route('sales.invoices.store'), $payload)
            ->assertSessionHasErrors(['items.0.qty']);

        tenancy()->initialize($tenantId);
        $this->assertSame(0, DB::table('sales_invoices')->count());
        tenancy()->end();
    }

    public function test_future_invoice_date_is_rejected(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranch();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        tenancy()->initialize($tenantId);
        [$variant1, $variant2, $salesTaxId] = $this->createCatalog($branchBId);
        $customerId = $this->createContact($branchBId, 'customer');
        $employeeId = $this->createContact($branchBId, 'employee');
        $warehouseId = $this->regularWarehouseOf($branchBId);
        $this->receiveStock($warehouseId, $variant1, 25);
        $this->receiveStock($warehouseId, $variant2, 25);
        tenancy()->end();

        $payload = $this->invoicePayload($warehouseId, $customerId, $employeeId, $variant1, $variant2, $salesTaxId);
        $payload['invoice_date'] = now()->addDay()->toDateString();
        $payload['due_date'] = now()->addDays(30)->toDateString();

        $this->actingAs($user)->post(route('sales.invoices.store'), $payload)
            ->assertSessionHasErrors(['invoice_date']);
    }

    public function test_discount_bounds_are_rejected(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranch();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        tenancy()->initialize($tenantId);
        [$variant1, $variant2, $salesTaxId] = $this->createCatalog($branchBId);
        $customerId = $this->createContact($branchBId, 'customer');
        $employeeId = $this->createContact($branchBId, 'employee');
        $warehouseId = $this->regularWarehouseOf($branchBId);
        $this->receiveStock($warehouseId, $variant1, 25);
        $this->receiveStock($warehouseId, $variant2, 25);
        tenancy()->end();

        // Persen > 100 ditolak.
        $payload = $this->invoicePayload($warehouseId, $customerId, $employeeId, $variant1, $variant2, $salesTaxId);
        $payload['items'][0]['discount_value'] = 150;

        $this->actingAs($user)->post(route('sales.invoices.store'), $payload)
            ->assertSessionHasErrors(['items.0.discount_value']);

        // Nominal melebihi gross baris ditolak.
        $payload['items'][0]['discount_type'] = 'nominal';
        $payload['items'][0]['discount_value'] = 999999999;

        $this->actingAs($user)->post(route('sales.invoices.store'), $payload)
            ->assertSessionHasErrors(['items.0.discount_value']);

        // Diskon invoice nominal melebihi total setelah diskon per baris ditolak.
        $payload = $this->invoicePayload($warehouseId, $customerId, $employeeId, $variant1, $variant2, $salesTaxId);
        $payload['invoice_discount_type'] = 'nominal';
        $payload['invoice_discount_value'] = 999999999;

        $this->actingAs($user)->post(route('sales.invoices.store'), $payload)
            ->assertSessionHasErrors(['invoice_discount_value']);
    }

    public function test_warehouse_type_must_match_transaction_type(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranch();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        tenancy()->initialize($tenantId);
        [$variant1, $variant2, $salesTaxId] = $this->createCatalog($branchBId);
        $customerId = $this->createContact($branchBId, 'customer');
        $employeeId = $this->createContact($branchBId, 'employee');
        $consignmentId = (int) DB::table('warehouses')
            ->where('branch_id', $branchBId)
            ->where('warehouse_type', 'consignment')
            ->value('id');
        $this->receiveStock($consignmentId, $variant1, 25);
        $this->receiveStock($consignmentId, $variant2, 25);
        tenancy()->end();

        $payload = $this->invoicePayload($consignmentId, $customerId, $employeeId, $variant1, $variant2, $salesTaxId);
        $payload['transaction_type'] = 'regular';

        $this->actingAs($user)->post(route('sales.invoices.store'), $payload)
            ->assertSessionHasErrors(['warehouse_id']);
    }

    public function test_regular_transaction_may_use_retail_warehouse(): void
    {
        [$tenantId, $branchBId, $user] = $this->createCompanyWithMemberAndBranch();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        tenancy()->initialize($tenantId);
        [$variant1, $variant2, $salesTaxId] = $this->createCatalog($branchBId);
        $customerId = $this->createContact($branchBId, 'customer');
        $employeeId = $this->createContact($branchBId, 'employee');
        $retailId = (int) DB::table('warehouses')
            ->where('branch_id', $branchBId)
            ->where('warehouse_type', 'retail')
            ->value('id');
        $this->receiveStock($retailId, $variant1, 25);
        $this->receiveStock($retailId, $variant2, 25);
        tenancy()->end();

        $payload = $this->invoicePayload($retailId, $customerId, $employeeId, $variant1, $variant2, $salesTaxId);
        $payload['transaction_type'] = 'regular';

        $this->actingAs($user)->post(route('sales.invoices.store'), $payload)
            ->assertRedirect(route('sales.invoices.index'));

        tenancy()->initialize($tenantId);
        $inv = DB::table('sales_invoices')->where('branch_id', $branchBId)->first();
        $this->assertNotNull($inv);
        $this->assertSame(SalesInvoiceStatus::Approved->value, $inv->status);
        $this->assertSame(15, (int) $this->balanceOf($retailId, $variant1));
        tenancy()->end();
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(
        int $warehouseId,
        int $customerId,
        int $employeeId,
        int $variant1,
        int $variant2,
        int $salesTaxId,
    ): array {
        return [
            'customer_id' => $customerId,
            'customer_email' => 'buyer@acme.test',
            'transaction_type' => 'regular',
            'warehouse_id' => $warehouseId,
            'salesperson_id' => $employeeId,
            'payment_term' => 'NET 30',
            'invoice_date' => '2026-09-10',
            'due_date' => '2026-10-10',
            'is_tax_inclusive' => false,
            'invoice_discount_type' => 'percent',
            'invoice_discount_value' => 5,
            'items' => [
                [
                    'product_variant_id' => $variant1,
                    'qty' => 10,
                    'unit_price' => 15000,
                    'discount_type' => 'percent',
                    'discount_value' => 10,
                    'tax_id' => $salesTaxId,
                ],
                [
                    'product_variant_id' => $variant2,
                    'qty' => 5,
                    'unit_price' => 20000,
                    'discount_type' => 'nominal',
                    'discount_value' => 5000,
                    'tax_id' => $salesTaxId,
                ],
            ],
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int} [variant1, variant2, salesTaxId]
     */
    private function createCatalog(int $branchId): array
    {
        $suffix = uniqid();
        [, $salesTaxId] = EligibleTaxFixture::create($suffix);

        return [
            $this->createProductAndVariant($branchId, 'SLS-1-'.$suffix, 15000),
            $this->createProductAndVariant($branchId, 'SLS-2-'.$suffix, 20000),
            $salesTaxId,
        ];
    }

    private function createProductAndVariant(int $branchId, string $sku, float $sellingPrice): int
    {
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
            'is_sold' => true,
            'selling_price' => $sellingPrice,
            'is_inventory_tracked' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table('product_variants')->insertGetId([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'sku' => $sku,
            'variant_name' => 'Default',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createContact(int $branchId, string $type, ?string $email = null): int
    {
        return (int) DB::table('contacts')->insertGetId([
            'branch_id' => $branchId,
            'type' => $type,
            'name' => ucfirst($type).' '.uniqid(),
            'email' => $email,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function regularWarehouseOf(int $branchId): int
    {
        return (int) DB::table('warehouses')
            ->where('branch_id', $branchId)
            ->where('warehouse_type', 'regular')
            ->value('id');
    }

    private function receiveStock(int $warehouseId, int $variantId, int $qty): void
    {
        app(ReceivePurchaseStock::class)->execute([
            'warehouse_id' => $warehouseId,
            'product_variant_id' => $variantId,
            'qty' => $qty,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'sales_test',
            'source_id' => 1,
        ]);
    }

    private function balanceOf(int $warehouseId, int $variantId): mixed
    {
        return StockBalance::query()
            ->where('warehouse_id', $warehouseId)
            ->where('product_variant_id', $variantId)
            ->value('qty_on_hand');
    }

    /**
     * @return array{0: string|int, 1: int, 2: User}
     */
    private function createCompanyWithMemberAndBranch(): array
    {
        $id = uniqid('sin_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Sales Invoice Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchCode = 'BRB_'.uniqid();
        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => $branchCode,
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(CreateWarehousesForBranch::class)->execute((int) $hqBranchId, 'HQ', 'HQ Branch');
        app(CreateWarehousesForBranch::class)->execute((int) $branchBId, $branchCode, 'Branch B');

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
            'branch_id' => $branchBId,
        ]);

        return [$tenant->id, (int) $branchBId, $user];
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
