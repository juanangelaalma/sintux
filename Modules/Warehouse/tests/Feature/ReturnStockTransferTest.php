<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Models\CompanyUser;
use Modules\Product\Application\Product\EnsureVariantForBranch;
use Modules\Warehouse\Application\StockLayer\GetLayerLineage;
use Modules\Warehouse\Application\StockTransfer\CreateReturnStockTransfer;
use Modules\Warehouse\Application\StockTransfer\ReceiveStockTransfer;
use Modules\Warehouse\Application\StockTransfer\ShipStockTransfer;
use Tests\TestCase;

/**
 * Retur transfer stok (RTRF): cabang mengembalikan barang ke HQ
 * berdasarkan transfer outbound yang sudah diterima, supaya HQ bisa membuat retur
 * pembelian dengan provenance yang benar.
 */
class ReturnStockTransferTest extends TestCase
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

        parent::tearDown();
    }

    public function test_branch_user_can_create_return_transfer_from_transfer_page(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);
        $outboundId = $this->seedReceivedOutbound($ctx);
        tenancy()->end();

        session([
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $ctx['branchId'],
        ]);

        $this->actingAs($ctx['user'])
            ->get(route('warehouse.stock-transfers.show', $outboundId))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canReturn', true)
                ->has('returnOptions', 1)
                ->where('returnOptions.0.returnable_qty', 6));

        tenancy()->initialize($ctx['tenantId']);

        $this->actingAs($ctx['user'])
            ->post(route('warehouse.stock-transfers.return', $outboundId), [
                'origin_transfer_id' => $outboundId,
                'items' => [
                    ['stock_transfer_item_id' => $ctx['outboundItemId'], 'qty' => 2],
                ],
            ])
            ->assertRedirect();

        tenancy()->initialize($ctx['tenantId']);
        $this->assertSame(2, DB::table('stock_transfers')->count());
        tenancy()->end();
    }

    public function test_return_transfer_rejects_insufficient_stock_from_transfer_page(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);
        $outboundId = $this->seedReceivedOutbound($ctx);
        tenancy()->end();

        session([
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $ctx['branchId'],
        ]);

        $this->actingAs($ctx['user'])
            ->from(route('warehouse.stock-transfers.show', $outboundId))
            ->post(route('warehouse.stock-transfers.return', $outboundId), [
                'origin_transfer_id' => $outboundId,
                'items' => [
                    ['stock_transfer_item_id' => $ctx['outboundItemId'], 'qty' => 7],
                ],
            ])
            ->assertSessionHasErrors('items.0.qty');

        tenancy()->initialize($ctx['tenantId']);
        $this->assertSame(1, DB::table('stock_transfers')->count());
        tenancy()->end();
    }

    public function test_return_button_is_hidden_when_transfer_is_already_at_head_office(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);
        $outboundId = $this->seedReceivedOutbound($ctx);
        DB::table('stock_transfers')
            ->where('id', $outboundId)
            ->update(['to_warehouse_id' => $ctx['hqWarehouseId']]);
        tenancy()->end();

        session([
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $ctx['hqBranchId'],
        ]);

        $this->actingAs($ctx['user'])
            ->get(route('warehouse.stock-transfers.show', $outboundId))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canReturn', false)
                ->where('returnOptions', []));
    }

    public function test_return_transfer_store_requires_transfer_permission(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);
        $outboundId = $this->seedReceivedOutbound($ctx);
        tenancy()->end();

        // User tanpa CompanyUser sama sekali: tidak punya akses transfer.
        $outsider = User::factory()->create([
            'email' => 'outsider_'.uniqid().'@acme.test',
            'role' => 'user',
        ]);

        session([
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $ctx['hqBranchId'],
        ]);

        $this->actingAs($outsider)
            ->post(route('warehouse.stock-transfers.return', $outboundId), [
                'origin_transfer_id' => $outboundId,
                'items' => [
                    ['stock_transfer_item_id' => $ctx['outboundItemId'], 'qty' => 1],
                ],
            ])
            ->assertForbidden();

        tenancy()->initialize($ctx['tenantId']);
        $this->assertSame(1, DB::table('stock_transfers')->count());
        tenancy()->end();
    }

    public function test_return_transfer_rejects_origin_from_other_tenant(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);
        $outboundId = $this->seedReceivedOutbound($ctx);
        tenancy()->end();

        $otherCtx = $this->seedContext();
        tenancy()->initialize($otherCtx['tenantId']);
        // Id transfer di tenant lain sengaja dibuat berbeda supaya test
        // benar-benar membuktikan id tidak bisa dicampur antar tenant,
        // bukan hanya kebetulan bernilai sama.
        DB::table('stock_transfers')->insert([
            'from_warehouse_id' => $otherCtx['hqWarehouseId'],
            'to_warehouse_id' => $otherCtx['branchWarehouseId'],
            'number' => 'TRF-'.substr(uniqid(), -10),
            'status' => 'received',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $otherOutboundId = $this->seedReceivedOutbound($otherCtx);
        tenancy()->end();

        $this->assertNotSame($outboundId, $otherOutboundId);

        session([
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $ctx['branchId'],
        ]);

        // Body menunjuk transfer milik tenant lain; harus ditolak.
        $this->actingAs($ctx['user'])
            ->from(route('warehouse.stock-transfers.show', $outboundId))
            ->post(route('warehouse.stock-transfers.return', $outboundId), [
                'origin_transfer_id' => $otherOutboundId,
                'items' => [
                    ['stock_transfer_item_id' => $otherCtx['outboundItemId'], 'qty' => 1],
                ],
            ])
            ->assertSessionHasErrors('origin_transfer_id');

        tenancy()->initialize($ctx['tenantId']);
        $this->assertSame(1, DB::table('stock_transfers')->count());
        tenancy()->end();
    }

    public function test_return_transfer_reverses_direction_and_inherits_source(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);

        $outbound = $this->createReceivedOutbound($ctx);

        $rtrf = app(CreateReturnStockTransfer::class)->execute(
            [
                'origin_transfer_id' => $outbound['transfer_id'],
                'items' => [
                    ['stock_transfer_item_id' => $outbound['item_id'], 'qty' => 2],
                ],
            ],
            (int) $ctx['user']->id,
            $ctx['user']->name,
        );

        // Arah dibalik: branch -> HQ.
        $this->assertSame($ctx['branchWarehouseId'], (int) $rtrf->from_warehouse_id);
        $this->assertSame($ctx['hqWarehouseId'], (int) $rtrf->to_warehouse_id);

        // Rantai tidak putus: sumber RTRF mewarisi sumber TRF outbound.
        $this->assertSame('Modules\\Purchasing\\Models\\GoodsReceipt', (string) $rtrf->source_type);
        $this->assertSame(777, (int) $rtrf->source_id);
        $this->assertNotSame($outbound['transfer_id'], (int) $rtrf->source_id);

        tenancy()->end();
    }

    public function test_return_transfer_is_pending_approval_for_branch_to_hq(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);

        $outbound = $this->createReceivedOutbound($ctx);

        $rtrf = app(CreateReturnStockTransfer::class)->execute(
            [
                'origin_transfer_id' => $outbound['transfer_id'],
                'items' => [
                    ['stock_transfer_item_id' => $outbound['item_id'], 'qty' => 2],
                ],
            ],
            (int) $ctx['user']->id,
            $ctx['user']->name,
        );

        // Cabang -> HQ selalu lewat approval, tidak langsung draft.
        $this->assertSame('pending_approval', $rtrf->status);

        tenancy()->end();
    }

    public function test_return_transfer_rejects_unreceived_origin(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);

        $outbound = $this->createReceivedOutbound($ctx);
        DB::table('stock_transfers')->where('id', $outbound['transfer_id'])->update([
            'status' => 'shipped',
        ]);

        try {
            app(CreateReturnStockTransfer::class)->execute(
                [
                    'origin_transfer_id' => $outbound['transfer_id'],
                    'items' => [
                        ['stock_transfer_item_id' => $outbound['item_id'], 'qty' => 1],
                    ],
                ],
                (int) $ctx['user']->id,
                $ctx['user']->name,
            );
            $this->fail('Expected ValidationException for unreceived origin transfer.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('origin_transfer_id', $e->errors());
        }

        tenancy()->end();
    }

    public function test_return_transfer_rejects_origin_not_returning_to_head_office(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);

        $outbound = $this->createReceivedOutbound($ctx);

        // Arahkan ulang transfer asal dari cabang lain ke cabang ini,
        // sehingga tujuan retur bukan gudang Head Office.
        $otherBranchId = DB::table('branches')->insertGetId([
            'name' => 'Other Branch',
            'code' => 'OTH_'.uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $otherBranchId,
            'code' => 'WH-OTH-'.uniqid(),
            'name' => 'Other Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('stock_transfers')
            ->where('id', $outbound['transfer_id'])
            ->update(['from_warehouse_id' => $otherWarehouseId]);

        try {
            app(CreateReturnStockTransfer::class)->execute(
                [
                    'origin_transfer_id' => $outbound['transfer_id'],
                    'items' => [
                        ['stock_transfer_item_id' => $outbound['item_id'], 'qty' => 1],
                    ],
                ],
                (int) $ctx['user']->id,
                $ctx['user']->name,
            );
            $this->fail('Expected ValidationException for non-HQ return destination.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('origin_transfer_id', $e->errors());
        }

        $this->assertSame(1, DB::table('stock_transfers')->count());

        tenancy()->end();
    }

    public function test_return_transfer_rejects_origin_already_at_head_office(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);

        $outbound = $this->createReceivedOutbound($ctx);

        // Tandai transfer sudah berada di HQ; tidak ada retur yang dibuat.
        DB::table('stock_transfers')
            ->where('id', $outbound['transfer_id'])
            ->update(['to_warehouse_id' => $ctx['hqWarehouseId']]);

        try {
            app(CreateReturnStockTransfer::class)->execute(
                [
                    'origin_transfer_id' => $outbound['transfer_id'],
                    'items' => [
                        ['stock_transfer_item_id' => $outbound['item_id'], 'qty' => 1],
                    ],
                ],
                (int) $ctx['user']->id,
                $ctx['user']->name,
            );
            $this->fail('Expected ValidationException for origin already at HQ.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('origin_transfer_id', $e->errors());
        }

        $this->assertSame(1, DB::table('stock_transfers')->count());

        tenancy()->end();
    }

    public function test_return_transfer_rejects_qty_above_returnable_stock(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);

        $outbound = $this->createReceivedOutbound($ctx);

        // Habis 4 dari 6 yang tiba di branch, sisa 2 yang bisa dikembalikan.
        DB::table('stock_layers')
            ->where('warehouse_id', $ctx['branchWarehouseId'])
            ->decrement('qty_remaining', 4);

        try {
            app(CreateReturnStockTransfer::class)->execute(
                [
                    'origin_transfer_id' => $outbound['transfer_id'],
                    'items' => [
                        ['stock_transfer_item_id' => $outbound['item_id'], 'qty' => 3],
                    ],
                ],
                (int) $ctx['user']->id,
                $ctx['user']->name,
            );
            $this->fail('Expected ValidationException for qty above returnable stock.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('items.0.qty', $e->errors());
        }

        tenancy()->end();
    }

    public function test_returned_stock_lands_in_hq_with_lineage_back_to_purchase(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);

        $outbound = $this->createReceivedOutbound($ctx);

        $rtrf = app(CreateReturnStockTransfer::class)->execute(
            [
                'origin_transfer_id' => $outbound['transfer_id'],
                'items' => [
                    ['stock_transfer_item_id' => $outbound['item_id'], 'qty' => 2],
                ],
            ],
            (int) $ctx['user']->id,
            $ctx['user']->name,
        );

        // Simulasikan ship + receive oleh user yang sesuai.
        $this->shipAndReceive((int) $rtrf->id, $ctx);

        $hqLayer = DB::table('stock_layers')
            ->where('warehouse_id', $ctx['hqWarehouseId'])
            ->where('source_type', 'stock_transfer')
            ->where('source_id', $rtrf->id)
            ->first();

        $this->assertNotNull($hqLayer);

        // Root harus purchase order asal, bukan transfer.
        $this->assertSame('purchase_order', $hqLayer->root_source_type);
        $this->assertSame($ctx['poId'], (int) $hqLayer->root_source_id);

        // Rantai FBL -> TRF -> RTRF harus bisa ditelusuri.
        $chain = app(GetLayerLineage::class)->execute((int) $hqLayer->id);
        $this->assertCount(3, $chain, 'Rantai harus punya 3 hop: RTRF -> TRF -> PO.');
        $this->assertSame('stock_transfer', $chain[0]['source_type']);
        $this->assertSame('purchase_order', $chain[2]['source_type']);
        $this->assertSame($ctx['poId'], (int) $chain[2]['source_id']);

        tenancy()->end();
    }

    public function test_returned_stock_lands_on_hq_variant_not_branch_variant(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);

        $outbound = $this->createReceivedOutbound($ctx);

        $rtrf = app(CreateReturnStockTransfer::class)->execute(
            [
                'origin_transfer_id' => $outbound['transfer_id'],
                'items' => [
                    ['stock_transfer_item_id' => $outbound['item_id'], 'qty' => 2],
                ],
            ],
            (int) $ctx['user']->id,
            $ctx['user']->name,
        );

        $this->shipAndReceive((int) $rtrf->id, $ctx);

        $hqLayer = DB::table('stock_layers')
            ->where('warehouse_id', $ctx['hqWarehouseId'])
            ->where('source_id', $rtrf->id)
            ->first();

        // Stok fisik yang tiba di HQ harus memakai master HQ, bukan mirror branch.
        $this->assertSame($ctx['variantId'], (int) $hqLayer->product_variant_id);

        tenancy()->end();
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function shipAndReceive(int $rtrfId, array $ctx): void
    {
        // RTRF dibuat pending_approval; approval HQ dilewati di sini.
        DB::table('stock_transfers')->where('id', $rtrfId)->update([
            'status' => 'draft',
            'approved_at' => now(),
        ]);

        app(ShipStockTransfer::class)->execute($rtrfId, (int) $ctx['user']->id);

        $items = DB::table('stock_transfer_items')
            ->where('stock_transfer_id', $rtrfId)
            ->get();

        app(ReceiveStockTransfer::class)->execute(
            $rtrfId,
            $items->map(fn ($item): array => [
                'stock_transfer_item_id' => (int) $item->id,
                'qty_received' => (int) $item->qty_shipped,
            ])->all(),
            (int) $ctx['user']->id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('rtrf_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'RTRF Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);
        tenancy()->initialize($tenant);

        $hqBranchId = DB::table('branches')->insertGetId([
            'name' => 'HQ', 'code' => 'HQ_'.uniqid(),
            'is_headquarters' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchId = DB::table('branches')->insertGetId([
            'name' => 'AII', 'code' => 'AII_'.uniqid(),
            'is_headquarters' => false, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $hqWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId, 'code' => 'WH-HQ-'.uniqid(),
            'name' => 'HQ Regular', 'warehouse_type' => 'regular',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $branchWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchId, 'code' => 'WH-AII-'.uniqid(),
            'name' => 'AII Regular', 'warehouse_type' => 'regular',
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Cat '.uniqid(), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS '.uniqid(), 'code' => 'PCS'.uniqid(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'branch_id' => $hqBranchId, 'code' => 'PRD-'.uniqid(),
            'name' => 'Widget', 'category_id' => $catId, 'uom_id' => $uomId,
            'is_inventory_tracked' => true, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $hqBranchId, 'product_id' => $productId,
            'sku' => 'SKU-'.uniqid(), 'variant_name' => 'Widget',
            'attributes' => json_encode(['color' => 'blue']),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $poId = DB::table('purchase_orders')->insertGetId([
            'number' => 'PO-'.uniqid(), 'branch_id' => $hqBranchId,
            'supplier_id' => DB::table('contacts')->insertGetId([
                'branch_id' => $hqBranchId, 'type' => 'supplier',
                'name' => 'Supplier '.uniqid(), 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]),
            'status' => 'received', 'order_date' => now()->toDateString(),
            'currency_code' => 'IDR', 'subtotal' => 0, 'tax_amount' => 0,
            'total' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'rtrf_'.$id.'@acme.test', 'role' => 'user',
        ]);
        // Role owner sudah membawa warehouse.stock.transfer lewat
        // RolePermissionSeeder, jadi user bisa membuat RTRF.
        $companyUser = CompanyUser::create([
            'user_id' => $user->id, 'tenant_id' => $tenant->id,
            'role' => 'owner', 'is_default' => true,
        ]);
        DB::table('company_user_branches')->insert([
            ['company_user_id' => $companyUser->id, 'branch_id' => $hqBranchId],
            ['company_user_id' => $companyUser->id, 'branch_id' => $branchId],
        ]);

        $this->seed(RolePermissionSeeder::class);

        return [
            'tenantId' => $tenant->id,
            'hqBranchId' => (int) $hqBranchId,
            'branchId' => (int) $branchId,
            'hqWarehouseId' => (int) $hqWarehouseId,
            'branchWarehouseId' => (int) $branchWarehouseId,
            'variantId' => (int) $variantId,
            'poId' => (int) $poId,
            'user' => $user,
        ];
    }

    /**
     * Bangun TRF outbound HQ -> branch yang sudah received, dengan layer
     * di branch yang root-nya menunjuk PO asal.
     *
     * @param  array<string, mixed>  $ctx
     * @return array{transfer_id: int, item_id: int, branch_variant_id: int}
     */
    private function createReceivedOutbound(array &$ctx): array
    {
        $outboundId = $this->seedReceivedOutbound($ctx);

        return [
            'transfer_id' => $outboundId,
            'item_id' => $ctx['outboundItemId'],
            'branch_variant_id' => $ctx['branchVariantId'],
        ];
    }

    /**
     * Mengisi $ctx['outboundItemId'] dan $ctx['branchVariantId'] lewat
     * reference supaya pemanggil ikut melihatnya. Tenancy harus sudah
     * di-initialize oleh pemanggil dan dibiarkan terbuka.
     *
     * @param  array<string, mixed>  $ctx
     */
    private function seedReceivedOutbound(array &$ctx): int
    {
        // Layer asal di HQ, root = PO.
        $originLayerId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $ctx['variantId'],
            'warehouse_id' => $ctx['hqWarehouseId'],
            'qty_remaining' => 6, 'unit_cost' => 47500,
            'received_at' => now(),
            'source_type' => 'purchase_order', 'source_id' => $ctx['poId'],
            'root_source_type' => 'purchase_order', 'root_source_id' => $ctx['poId'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stock_balances')->insert([
            'warehouse_id' => $ctx['hqWarehouseId'],
            'product_variant_id' => $ctx['variantId'],
            'qty_on_hand' => 6, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $outboundId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => null,
            'from_warehouse_id' => $ctx['hqWarehouseId'],
            'to_warehouse_id' => $ctx['branchWarehouseId'],
            'number' => 'TRF-'.substr(uniqid(), -10),
            'status' => 'received',
            'source_type' => 'Modules\\Purchasing\\Models\\GoodsReceipt',
            'source_id' => 777,
            'created_by' => $ctx['user']->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $itemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $outboundId,
            'product_variant_id' => $ctx['variantId'],
            'qty' => 6, 'qty_shipped' => 6, 'qty_received' => 6,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stock_transfer_item_layers')->insert([
            'stock_transfer_item_id' => $itemId,
            'stock_layer_id' => $originLayerId,
            'qty_taken' => 6, 'unit_cost' => 47500,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Layer di branch: mirror variant, root PO, parent = layer asal.
        $branchVariantId = app(EnsureVariantForBranch::class)
            ->execute((int) $ctx['variantId'], (int) $ctx['branchId']);

        DB::table('stock_layers')->insert([
            'product_variant_id' => $branchVariantId,
            'warehouse_id' => $ctx['branchWarehouseId'],
            'qty_remaining' => 6, 'unit_cost' => 47500,
            'received_at' => now(),
            'source_type' => 'stock_transfer', 'source_id' => $outboundId,
            'root_source_type' => 'purchase_order', 'root_source_id' => $ctx['poId'],
            'parent_layer_id' => $originLayerId,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('stock_balances')->insert([
            'warehouse_id' => $ctx['branchWarehouseId'],
            'product_variant_id' => $branchVariantId,
            'qty_on_hand' => 6, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $ctx['outboundItemId'] = (int) $itemId;
        $ctx['branchVariantId'] = (int) $branchVariantId;

        return (int) $outboundId;
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
