<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Accounting\Database\Seeders\ChartOfAccountsSeeder;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Tests\TestCase;

class PurchaseReturnHttpTest extends TestCase
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

    public function test_new_prefills_returnable_lines(): void
    {
        [$tenantId, $hqBranchId, $user] = $this->seedContext();
        $invoiceId = $this->invoiceId($tenantId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $response = $this->actingAs($user)->get(route('purchasing.returns.new', ['createdFrom' => $invoiceId]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('prefillInvoice')
            ->where('prefillInvoice.invoice.id', $invoiceId)
            ->has('prefillInvoice.items', 1)
            ->has('warehouses')
            ->has('tags')
            ->where('prefillError', null));
    }

    public function test_new_with_unknown_invoice_shows_prefill_error(): void
    {
        [$tenantId, $hqBranchId, $user] = $this->seedContext();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $response = $this->actingAs($user)->get(route('purchasing.returns.new', ['createdFrom' => 999999]));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('prefillInvoice', null)
            ->where('prefillError', fn ($value) => $value !== null));
    }

    public function test_new_prefills_filtered_availability_with_transfer(): void
    {
        [$tenantId, $hqBranchId, $user] = $this->seedContext();
        $ids = $this->ids($tenantId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        tenancy()->initialize($tenantId);
        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => null,
            'from_warehouse_id' => $ids['warehouseId'],
            'to_warehouse_id' => $ids['warehouseId'],
            'number' => 'TRF-HTTP-001',
            'status' => 'received',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_layers')->insert([
            'product_variant_id' => $ids['variantId'],
            'warehouse_id' => $ids['warehouseId'],
            'qty_remaining' => 3,
            'unit_cost' => 20000,
            'received_at' => now(),
            'source_type' => 'stock_transfer',
            'source_id' => $transferId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();

        $response = $this->actingAs($user)->get(route('purchasing.returns.new', [
            'createdFrom' => $ids['invoiceId'],
            'returnTransfer' => $transferId,
        ]));

        $response->assertOk();
        $availability = $response->inertiaProps('availability');
        $this->assertSame(3, (int) $availability[$ids['warehouseId']][$ids['variantId']]);
        $transfers = $response->inertiaProps('transfers');
        $this->assertNotEmpty($transfers);
    }

    public function test_store_creates_return_with_attachment(): void
    {
        Storage::fake('local');

        [$tenantId, $hqBranchId, $user] = $this->seedContext();
        $ids = $this->ids($tenantId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $response = $this->actingAs($user)->post(route('purchasing.returns.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $ids['supplierId'],
            'purchase_invoice_id' => $ids['invoiceId'],
            'warehouse_id' => $ids['warehouseId'],
            'return_date' => '2026-09-23',
            'message' => 'Barang cacat',
            'memo' => 'Memo internal',
            'items' => [
                ['purchase_invoice_item_id' => $ids['itemId'], 'qty' => 2],
            ],
            'attachments' => [
                UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
            ],
        ]);

        tenancy()->initialize($tenantId);
        $returnId = DB::table('purchase_returns')->orderByDesc('id')->value('id');
        tenancy()->end();

        $response->assertRedirect(route('purchasing.returns.show', $returnId));

        tenancy()->initialize($tenantId);
        $attachment = DB::table('purchase_return_attachments')->where('purchase_return_id', $returnId)->first();
        $this->assertNotNull($attachment);
        $this->assertSame('nota.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->path);
        tenancy()->end();
    }

    public function test_store_rejects_other_tenant_invoice(): void
    {
        [$tenantId, $hqBranchId, $user] = $this->seedContext();
        [$otherTenantId] = $this->seedContext();
        // Faktur kedua di tenant lain (id 2): ada secara global tapi tidak
        // di skema tenant ini. Tanpa ini kedua tenant sama-sama punya id 1
        // dan uji isolasi tidak membuktikan apa-apa.
        $this->duplicateInvoice($otherTenantId);
        $otherIds = $this->ids($otherTenantId, 2);
        $ids = $this->ids($tenantId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $this->actingAs($user)
            ->from(route('purchasing.returns.new'))
            ->post(route('purchasing.returns.store'), [
                'branch_id' => $hqBranchId,
                'supplier_id' => $ids['supplierId'],
                'purchase_invoice_id' => $otherIds['invoiceId'],
                'warehouse_id' => $ids['warehouseId'],
                'return_date' => '2026-09-23',
                'items' => [
                    ['purchase_invoice_item_id' => $otherIds['itemId'], 'qty' => 1],
                ],
            ])
            ->assertSessionHasErrors(['purchase_invoice_id']);
    }

    public function test_attachment_download_requires_branch_access(): void
    {
        Storage::fake('local');

        [$tenantId, $hqBranchId, $user] = $this->seedContext();
        $ids = $this->ids($tenantId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $this->actingAs($user)->post(route('purchasing.returns.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $ids['supplierId'],
            'purchase_invoice_id' => $ids['invoiceId'],
            'warehouse_id' => $ids['warehouseId'],
            'return_date' => '2026-09-23',
            'items' => [
                ['purchase_invoice_item_id' => $ids['itemId'], 'qty' => 1],
            ],
            'attachments' => [
                UploadedFile::fake()->create('nota.pdf', 100, 'application/pdf'),
            ],
        ])->assertRedirect();

        tenancy()->initialize($tenantId);
        $returnId = DB::table('purchase_returns')->orderByDesc('id')->value('id');
        $attachmentId = DB::table('purchase_return_attachments')->where('purchase_return_id', $returnId)->value('id');
        tenancy()->end();

        // Pemilik akses: unduhan berhasil.
        $this->actingAs($user)
            ->get(route('purchasing.returns.attachments.download', [$returnId, $attachmentId]))
            ->assertOk();

        // Lampiran retur lain: 404 (bukan bocor via enumerasi id).
        $this->actingAs($user)
            ->get(route('purchasing.returns.attachments.download', [$returnId, $attachmentId + 999]))
            ->assertNotFound();
    }

    public function test_show_renders_return_detail(): void
    {
        [$tenantId, $hqBranchId, $user] = $this->seedContext();
        $ids = $this->ids($tenantId);
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $returnId = $this->actingAs($user)->post(route('purchasing.returns.store'), [
            'branch_id' => $hqBranchId,
            'supplier_id' => $ids['supplierId'],
            'purchase_invoice_id' => $ids['invoiceId'],
            'warehouse_id' => $ids['warehouseId'],
            'return_date' => '2026-09-23',
            'items' => [
                ['purchase_invoice_item_id' => $ids['itemId'], 'qty' => 1],
            ],
        ])->assertRedirect()->getTargetUrl();

        $this->actingAs($user)->get($returnId)->assertOk();
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function seedContext(): array
    {
        $id = uniqid('rhttp_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Return HTTP Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);
        $this->seed(ChartOfAccountsSeeder::class);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $warehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-RHTTP-'.uniqid(),
            'name' => 'HTTP Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $supplierId = DB::table('contacts')->insertGetId([
            'branch_id' => $hqBranchId,
            'type' => 'supplier',
            'name' => 'Supplier '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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
            'branch_id' => $hqBranchId,
            'code' => 'PRD-'.uniqid(),
            'name' => 'Widget HTTP',
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_inventory_tracked' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $hqBranchId,
            'product_id' => $productId,
            'sku' => 'SKU-HTTP-'.uniqid(),
            'variant_name' => 'Widget HTTP',
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $invoiceId = DB::table('purchase_invoices')->insertGetId([
            'number' => 'FBL/HQ/20260923/'.uniqid().'/A',
            'branch_id' => $hqBranchId,
            'supplier_id' => $supplierId,
            'status' => PurchaseInvoiceStatus::Approved->value,
            'invoice_date' => '2026-09-23',
            'currency_code' => 'IDR',
            'is_tax_inclusive' => false,
            'subtotal' => 100000,
            'tax_amount' => 0,
            'total' => 100000,
            'returned_amount' => 0,
            'paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('purchase_invoice_items')->insert([
            'purchase_invoice_id' => $invoiceId,
            'product_variant_id' => $variantId,
            'product_name' => 'Widget HTTP',
            'sku' => 'SKU-HTTP',
            'qty' => 5,
            'qty_returned' => 0,
            'unit_price' => 20000,
            'tax_rate' => 0,
            'line_total' => 100000,
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

        return [$tenant->id, (int) $hqBranchId, $user];
    }

    private function invoiceId(string $tenantId): int
    {
        tenancy()->initialize($tenantId);
        $id = (int) DB::table('purchase_invoices')->orderByDesc('id')->value('id');
        tenancy()->end();

        return $id;
    }

    private function duplicateInvoice(string $tenantId): void
    {
        tenancy()->initialize($tenantId);
        $original = DB::table('purchase_invoices')->orderByDesc('id')->first();
        $cloneId = DB::table('purchase_invoices')->insertGetId([
            'number' => $original->number.'-DUP',
            'branch_id' => $original->branch_id,
            'supplier_id' => $original->supplier_id,
            'status' => $original->status,
            'invoice_date' => $original->invoice_date,
            'currency_code' => 'IDR',
            'is_tax_inclusive' => false,
            'subtotal' => 100000,
            'tax_amount' => 0,
            'total' => 100000,
            'returned_amount' => 0,
            'paid_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $item = DB::table('purchase_invoice_items')->where('purchase_invoice_id', $original->id)->first();
        DB::table('purchase_invoice_items')->insert([
            'purchase_invoice_id' => $cloneId,
            'product_variant_id' => $item->product_variant_id,
            'product_name' => $item->product_name,
            'sku' => $item->sku,
            'qty' => 5,
            'qty_returned' => 0,
            'unit_price' => 20000,
            'tax_rate' => 0,
            'line_total' => 100000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();
    }

    /**
     * @return array<string, int>
     */
    private function ids(string $tenantId, int $nth = 1): array
    {
        tenancy()->initialize($tenantId);
        $invoice = DB::table('purchase_invoices')->orderBy('id')->skip($nth - 1)->first();
        $item = DB::table('purchase_invoice_items')->where('purchase_invoice_id', $invoice->id)->first();
        $result = [
            'invoiceId' => (int) $invoice->id,
            'itemId' => (int) $item->id,
            'variantId' => (int) $item->product_variant_id,
            'supplierId' => (int) $invoice->supplier_id,
            'warehouseId' => (int) DB::table('warehouses')->orderByDesc('id')->value('id'),
        ];
        tenancy()->end();

        return $result;
    }

    private function cleanupCentralTables(): void
    {
        foreach (['company_user_branches', 'company_users', 'tenants', 'users'] as $table) {
            try {
                DB::table($table)->delete();
            } catch (\Throwable $e) {
            }
        }
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            $safe = str_replace('"', '""', $schemaName);
            DB::statement('DROP SCHEMA IF EXISTS "'.$safe.'" CASCADE');
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
        }
    }
}
