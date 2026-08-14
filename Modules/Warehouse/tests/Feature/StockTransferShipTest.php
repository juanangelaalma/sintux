<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class StockTransferShipTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropLeftoverSchemas();

        /*
         * Clean central/test data.
         *
         * Stock request / transfer tables are tenant-scoped,
         * therefore they are cleaned when the tenant schema is dropped.
         */
        DB::table('company_user_branches')->delete();
        DB::table('company_users')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
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

    /**
     * A transfer with one stock layer can be shipped successfully.
     */
    public function test_ship_consumes_single_layer_correctly(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] =
            $this->createCompanyWithMemberAndBranches();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $hqBranchId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        /*
         * HQ stock: 20
         */
        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 20,
        ]);

        /*
         * FIFO layer: 20 @ 10000
         */
        $layerId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 20,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Stock request.
         */
        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'Test request',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Transfer.
         */
        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => $stockRequestId,
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $branchBWarehouseId,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferItemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variant1Id,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        /*
         * Ship.
         */
        $response = $this->actingAs($user)
            ->post(
                route(
                    'warehouse.stock-transfers.ship',
                    $transferId
                )
            );

        $response->assertRedirect(
            route(
                'warehouse.stock-transfers.show',
                $transferId
            )
        );

        tenancy()->initialize($tenantId);

        /*
         * Transfer status.
         */
        $transfer = DB::table('stock_transfers')
            ->where('id', $transferId)
            ->first();

        $this->assertNotNull($transfer);

        $this->assertSame(
            'shipped',
            $transfer->status
        );

        $this->assertSame(
            $user->id,
            $transfer->shipped_by
        );

        $this->assertNotNull(
            $transfer->shipped_at
        );

        /*
         * Layer consumption.
         */
        $layerUsage = DB::table('stock_transfer_item_layers')
            ->where(
                'stock_transfer_item_id',
                $transferItemId
            )
            ->first();

        $this->assertNotNull($layerUsage);

        /*
         * IMPORTANT:
         * Expected value comes FIRST.
         */
        $this->assertSame(
            $layerId,
            (int) $layerUsage->stock_layer_id
        );

        $this->assertSame(
            5,
            (int) $layerUsage->qty_taken
        );

        $this->assertSame(
            10000,
            (int) $layerUsage->unit_cost
        );

        /*
         * Source stock decreased.
         */
        $sourceStock = DB::table('stock_balances')
            ->where('warehouse_id', $hqWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNotNull($sourceStock);

        $this->assertSame(
            15,
            (int) $sourceStock->qty_on_hand
        );

        /*
         * Destination stock increased.
         */
        $destinationStock = DB::table('stock_balances')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNotNull($destinationStock);

        $this->assertSame(
            5,
            (int) $destinationStock->qty_on_hand
        );

        /*
         * Layer remaining quantity.
         */
        $layer = DB::table('stock_layers')
            ->where('id', $layerId)
            ->first();

        $this->assertNotNull($layer);

        $this->assertSame(
            15,
            (int) $layer->qty_remaining
        );

        /*
         * Stock movement recorded for audit trail.
         */
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $hqWarehouseId,
            'product_variant_id' => $variant1Id,
            'movement_type' => 'transfer_out',
            'qty' => -5,
            'unit_cost' => 10000,
            'stock_layer_id' => $layerId,
            'reference_type' => \Modules\Warehouse\Models\StockTransfer::class,
            'reference_id' => $transferId,
        ]);

        tenancy()->end();
    }

    /**
     * Shipping must consume stock layers using FIFO order.
     */
    public function test_ship_consumes_multiple_layers_in_fifo_order(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] =
            $this->createCompanyWithMemberAndBranches();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $hqBranchId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        /*
         * -----------------------------------------------------
         * FIFO LAYERS
         * -----------------------------------------------------
         *
         * Layer 1:
         * 10 @ 9000
         *
         * Layer 2:
         * 10 @ 11000
         *
         * Total = 20
         */

        $layer1ReceivedAt = now()->subDays(2);

        $layer1Id = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 10,
            'unit_cost' => 9000,
            'received_at' => $layer1ReceivedAt,
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'created_at' => $layer1ReceivedAt,
            'updated_at' => $layer1ReceivedAt,
        ]);

        $layer2ReceivedAt = now()->subDay();

        $layer2Id = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 10,
            'unit_cost' => 11000,
            'received_at' => $layer2ReceivedAt,
            'source_type' => 'purchase_order',
            'source_id' => 2,
            'created_at' => $layer2ReceivedAt,
            'updated_at' => $layer2ReceivedAt,
        ]);

        /*
         * Stock balance = 20.
         */
        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 20,
        ]);

        /*
         * -----------------------------------------------------
         * STOCK REQUEST
         * -----------------------------------------------------
         */

        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'FIFO test request',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * -----------------------------------------------------
         * STOCK TRANSFER
         * -----------------------------------------------------
         */

        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => $stockRequestId,
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $branchBWarehouseId,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Request 15.
         *
         * Expected FIFO:
         *
         * Layer 1 -> 10
         * Layer 2 -> 5
         */
        $transferItemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variant1Id,
            'qty' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        /*
         * -----------------------------------------------------
         * SHIP
         * -----------------------------------------------------
         */

        $response = $this->actingAs($user)
            ->post(
                route(
                    'warehouse.stock-transfers.ship',
                    $transferId
                )
            );

        $response->assertRedirect(
            route(
                'warehouse.stock-transfers.show',
                $transferId
            )
        );

        tenancy()->initialize($tenantId);

        /*
         * Transfer must be shipped.
         */
        $transfer = DB::table('stock_transfers')
            ->where('id', $transferId)
            ->first();

        $this->assertNotNull($transfer);

        $this->assertSame(
            'shipped',
            $transfer->status
        );

        /*
         * -----------------------------------------------------
         * CHECK FIFO CONSUMPTION
         * -----------------------------------------------------
         */

        $layers = DB::table('stock_transfer_item_layers')
            ->where(
                'stock_transfer_item_id',
                $transferItemId
            )
            ->orderBy('id')
            ->get();

        /*
         * 15 units must come from exactly 2 layers.
         */
        $this->assertCount(
            2,
            $layers
        );

        /*
         * First consumed layer must be layer 1.
         *
         * IMPORTANT:
         * Expected value FIRST.
         *
         * The old test had:
         *
         * assertEquals($actual, $expected)
         *
         * which caused misleading failures such as:
         * "2 matches expected 72".
         */
        $this->assertSame(
            $layer1Id,
            (int) $layers[0]->stock_layer_id
        );

        $this->assertSame(
            10,
            (int) $layers[0]->qty_taken
        );

        $this->assertSame(
            9000,
            (int) $layers[0]->unit_cost
        );

        /*
         * Second consumed layer must be layer 2.
         */
        $this->assertSame(
            $layer2Id,
            (int) $layers[1]->stock_layer_id
        );

        $this->assertSame(
            5,
            (int) $layers[1]->qty_taken
        );

        $this->assertSame(
            11000,
            (int) $layers[1]->unit_cost
        );

        /*
         * -----------------------------------------------------
         * CHECK REMAINING LAYER QUANTITIES
         * -----------------------------------------------------
         */

        $layer1 = DB::table('stock_layers')
            ->where('id', $layer1Id)
            ->first();

        $this->assertNotNull($layer1);

        /*
         * 10 - 10 = 0
         */
        $this->assertSame(
            0,
            (int) $layer1->qty_remaining
        );

        $layer2 = DB::table('stock_layers')
            ->where('id', $layer2Id)
            ->first();

        $this->assertNotNull($layer2);

        /*
         * 10 - 5 = 5
         */
        $this->assertSame(
            5,
            (int) $layer2->qty_remaining
        );

        /*
         * -----------------------------------------------------
         * CHECK STOCK BALANCE
         * -----------------------------------------------------
         *
         * 20 - 15 = 5
         */
        $stockBalance = DB::table('stock_balances')
            ->where('product_variant_id', $variant1Id)
            ->where('warehouse_id', $hqWarehouseId)
            ->first();

        $this->assertNotNull($stockBalance);

        $this->assertSame(
            5,
            (int) $stockBalance->qty_on_hand
        );

        /*
         * Destination receives 15.
         */
        $destinationStock = DB::table('stock_balances')
            ->where('product_variant_id', $variant1Id)
            ->where('warehouse_id', $branchBWarehouseId)
            ->first();

        $this->assertNotNull($destinationStock);

        $this->assertSame(
            15,
            (int) $destinationStock->qty_on_hand
        );

        tenancy()->end();
    }

    /**
     * Shipping must fail when total available stock is insufficient.
     */
    public function test_ship_fails_when_total_stock_insufficient(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] =
            $this->createCompanyWithMemberAndBranches();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $hqBranchId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        /*
         * Only 3 units available.
         */
        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 3,
        ]);

        $layerId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 3,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Stock request.
         */
        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'Insufficient stock test',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Transfer.
         */
        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => $stockRequestId,
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $branchBWarehouseId,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Request 5 while only 3 exist.
         */
        $transferItemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variant1Id,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        /*
         * -----------------------------------------------------
         * SHIP
         * -----------------------------------------------------
         */

        $response = $this->actingAs($user)
            ->post(
                route(
                    'warehouse.stock-transfers.ship',
                    $transferId
                )
            );

        /*
         * The application should redirect back with
         * a validation error.
         */
        $response->assertRedirect();

        $response->assertSessionHasErrors([
            'stock_transfer',
        ]);

        /*
         * Verify message content without relying on
         * wildcard matching.
         */
        $errors = session('errors');

        $this->assertNotNull($errors);

        $messages = $errors
            ->getBag('default')
            ->all();

        $this->assertTrue(
            collect($messages)->contains(
                fn (string $message): bool =>
                    str_contains(
                        strtolower($message),
                        'insufficient stock'
                    )
            ),
            'Expected an insufficient stock error.'
        );

        /*
         * -----------------------------------------------------
         * STOCK MUST NOT CHANGE
         * -----------------------------------------------------
         */

        tenancy()->initialize($tenantId);

        $layer = DB::table('stock_layers')
            ->where('id', $layerId)
            ->first();

        $this->assertNotNull($layer);

        $this->assertSame(
            3,
            (int) $layer->qty_remaining
        );

        $stockBalance = DB::table('stock_balances')
            ->where('product_variant_id', $variant1Id)
            ->where('warehouse_id', $hqWarehouseId)
            ->first();

        $this->assertNotNull($stockBalance);

        $this->assertSame(
            3,
            (int) $stockBalance->qty_on_hand
        );

        /*
         * No FIFO consumption record.
         */
        $this->assertDatabaseMissing(
            'stock_transfer_item_layers',
            [
                'stock_transfer_item_id' => $transferItemId,
            ]
        );

        /*
         * Transfer remains draft.
         */
        $transfer = DB::table('stock_transfers')
            ->where('id', $transferId)
            ->first();

        $this->assertNotNull($transfer);

        $this->assertSame(
            'draft',
            $transfer->status
        );

        tenancy()->end();
    }

    /**
     * A shipped/received transfer cannot be shipped again.
     */
    public function test_cannot_ship_already_shipped_or_received_transfer(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] =
            $this->createCompanyWithMemberAndBranches();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $hqBranchId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 20,
        ]);

        DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 20,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'Already processed test',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => $stockRequestId,
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $branchBWarehouseId,
            'status' => 'shipped',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_transfer_items')->insert([
            [
                'stock_transfer_id' => $transferId,
                'product_variant_id' => $variant1Id,
                'qty' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        tenancy()->end();

        /*
         * Try to ship an already shipped transfer.
         */
        $response = $this->actingAs($user)
            ->post(
                route(
                    'warehouse.stock-transfers.ship',
                    $transferId
                )
            );

        /*
         * Do not use:
         *
         * assertSessionHasErrors([
         *     'stock_transfer' => 'sudah diproses*'
         * ]);
         *
         * because wildcard matching in this form is not reliable
         * for the full error message.
         */
        $response->assertRedirect();

        $response->assertSessionHasErrors([
            'stock_transfer',
        ]);

        $errors = session('errors');

        $this->assertNotNull($errors);

        $messages = $errors
            ->getBag('default')
            ->all();

        $this->assertTrue(
            collect($messages)->contains(
                fn (string $message): bool =>
                    str_contains(
                        strtolower($message),
                        'sudah diproses'
                    )
            ),
            'Expected an already-processed transfer error.'
        );

        tenancy()->end();
    }

    /**
     * Source stock must decrease and destination stock must increase.
     */
    public function test_stock_balance_decreases_correctly_after_ship(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] =
            $this->createCompanyWithMemberAndBranches();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $hqBranchId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 100,
        ]);

        $layerId = DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 100,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'Balance test',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => $stockRequestId,
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $branchBWarehouseId,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_transfer_items')->insert([
            [
                'stock_transfer_id' => $transferId,
                'product_variant_id' => $variant1Id,
                'qty' => 30,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        tenancy()->end();

        $response = $this->actingAs($user)
            ->post(
                route(
                    'warehouse.stock-transfers.ship',
                    $transferId
                )
            );

        $response->assertRedirect(
            route(
                'warehouse.stock-transfers.show',
                $transferId
            )
        );

        tenancy()->initialize($tenantId);

        /*
         * Source: 100 - 30 = 70
         */
        $hqStock = DB::table('stock_balances')
            ->where('warehouse_id', $hqWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNotNull($hqStock);

        $this->assertSame(
            70,
            (int) $hqStock->qty_on_hand
        );

        /*
         * Destination: 0 + 30 = 30
         */
        $destStock = DB::table('stock_balances')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNotNull($destStock);

        $this->assertSame(
            30,
            (int) $destStock->qty_on_hand
        );

        /*
         * Layer: 100 - 30 = 70
         */
        $layer = DB::table('stock_layers')
            ->where('id', $layerId)
            ->first();

        $this->assertNotNull($layer);

        $this->assertSame(
            70,
            (int) $layer->qty_remaining
        );

        tenancy()->end();
    }

    /**
     * User without access to the source branch cannot ship.
     */
    public function test_user_without_transfer_permission_cannot_ship(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] =
            $this->createCompanyWithMemberAndBranches();

        /*
         * Create a user with access ONLY to Branch B.
         *
         * This user has no access to HQ, which is the
         * source warehouse of the transfer.
         */
        $branchOnlyUser = User::factory()->create([
            'email' => 'branch_only_' . uniqid() . '@acme.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::create([
            'user_id' => $branchOnlyUser->id,
            'tenant_id' => $tenantId,
            'role' => 'member',
            'is_default' => true,
        ]);

        DB::table('company_user_branches')->insert([
            'company_user_id' => $companyUser->id,
            'branch_id' => $branchBId,
        ]);

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchBId,
        ]);

        tenancy()->initialize($tenantId);

        [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variant1Id,
        ] = $this->seedWarehouseAndVariants(
            $hqBranchId,
            $branchBId
        );

        /*
         * Source stock.
         */
        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 20,
        ]);

        DB::table('stock_layers')->insertGetId([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_remaining' => 20,
            'unit_cost' => 10000,
            'received_at' => now(),
            'source_type' => 'purchase_order',
            'source_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * IMPORTANT:
         *
         * The original test used $stockRequestId here
         * without creating it first.
         *
         * That caused:
         *
         * Undefined variable $stockRequestId
         *
         * Create the stock request first.
         */
        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'Permission test',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Create transfer from HQ -> Branch B.
         */
        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => $stockRequestId,
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $branchBWarehouseId,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('stock_transfer_items')->insert([
            [
                'stock_transfer_id' => $transferId,
                'product_variant_id' => $variant1Id,
                'qty' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        tenancy()->end();

        /*
         * Branch-B-only user attempts to ship a transfer
         * whose source warehouse is HQ.
         */
        $response = $this->actingAs($branchOnlyUser)
            ->post(
                route(
                    'warehouse.stock-transfers.ship',
                    $transferId
                )
            );

        /*
         * Must be forbidden.
         */
        $response->assertStatus(403);
    }

    /**
     * @return array{
     *     0: string,
     *     1: int,
     *     2: int,
     *     3: User
     * }
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('ship_');
        $schemaName = 'sch_' . $id;

        $this->activeSchemaName = $schemaName;

        /*
         * Create tenant.
         */
        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Ship Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        /*
         * Run tenant migrations.
         */
        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
        ]);

        tenancy()->initialize($tenant);

        /*
         * HQ branch.
         */
        $existingHq = DB::table('branches')
            ->where('code', 'HQ')
            ->value('id');

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

        /*
         * Branch B.
         */
        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_' . uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        /*
         * Owner/member user.
         */
        $user = User::factory()->create([
            'email' => 'owner_' . $id . '@acme.test',
            'role' => 'user',
        ]);

        $companyUser = CompanyUser::create([
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'is_default' => true,
        ]);

        /*
         * Owner has access to both branches.
         */
        DB::table('company_user_branches')->insert([
            [
                'company_user_id' => $companyUser->id,
                'branch_id' => $hqBranchId,
            ],
            [
                'company_user_id' => $companyUser->id,
                'branch_id' => $branchBId,
            ],
        ]);

        return [
            $tenant->id,
            (int) $hqBranchId,
            (int) $branchBId,
            $user,
        ];
    }

    /**
     * Create warehouses and a product variant for the tenant.
     *
     * @return array{
     *     0: int,
     *     1: int,
     *     2: int
     * }
     */
    private function seedWarehouseAndVariants(
        int $hqBranchId,
        int $branchBId
    ): array {
        /*
         * HQ warehouse.
         */
        $hqWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-HQ-' . uniqid(),
            'name' => 'HQ Central Warehouse',
            'warehouse_type' => 'general',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Branch B warehouse.
         */
        $branchBWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-BRB-' . uniqid(),
            'name' => 'Branch B Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Product category.
         */
        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Category ' . uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * UOM.
         */
        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS ' . uniqid(),
            'code' => 'PCS' . uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Product.
         */
        $productId = DB::table('products')->insertGetId([
            'code' => 'PRD-' . uniqid(),
            'name' => 'Widget ' . uniqid(),
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /*
         * Product variant.
         */
        $variantId = DB::table('product_variants')->insertGetId([
            'product_id' => $productId,
            'sku' => 'SKU-' . uniqid(),
            'variant_name' => 'Widget Variant ' . uniqid(),
            'attributes' => json_encode([
                'color' => 'blue',
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            $hqWarehouseId,
            $branchBWarehouseId,
            $variantId,
        ];
    }

    private function dropSchema(string $schemaName): void
    {
        try {
            DB::statement(
                'DROP SCHEMA IF EXISTS "' .
                $schemaName .
                '" CASCADE'
            );
        } catch (\Exception $e) {
            // Ignore cleanup failures.
        }
    }

    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "
                SELECT schema_name
                FROM information_schema.schemata
                WHERE schema_name NOT IN (
                    'public',
                    'information_schema'
                )
                AND schema_name NOT LIKE 'pg_%'
                "
            );

            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;

                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Exception $e) {
            // Ignore cleanup failures.
        }
    }
}