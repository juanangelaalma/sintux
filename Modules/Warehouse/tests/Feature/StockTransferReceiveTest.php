<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class StockTransferReceiveTest extends TestCase
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
     * A shipped transfer can be received (status received),
     * destination layers are created and transfer_in movements recorded.
     */
    public function test_receive_marks_transfer_as_received_and_creates_destination_layers(): void
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
         * Source stock: 20.
         */
        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 20,
        ]);

        /*
         * FIFO layer: 20 @ 10000.
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

        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'Receive test request',
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

        $transferItemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variant1Id,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        /*
         * Ship first.
         */
        $this->actingAs($user)
            ->post(
                route(
                    'warehouse.stock-transfers.ship',
                    $transferId
                )
            )
            ->assertRedirect(
                route(
                    'warehouse.stock-transfers.show',
                    $transferId
                )
            );

        tenancy()->initialize($tenantId);

        /*
         * Pre-receive state: destination balance already 5 from ship,
         * breakdown recorded on stock_transfer_item_layers.
         */
        $breakdown = DB::table('stock_transfer_item_layers')
            ->where('stock_transfer_item_id', $transferItemId)
            ->get();

        $this->assertCount(1, $breakdown);

        $this->assertSame(
            $layerId,
            (int) $breakdown[0]->stock_layer_id
        );

        tenancy()->end();

        /*
         * Receive.
         */
        $response = $this->actingAs($user)
            ->post(
                route(
                    'warehouse.stock-transfers.receive',
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
         * Transfer must be received.
         */
        $transfer = DB::table('stock_transfers')
            ->where('id', $transferId)
            ->first();

        $this->assertNotNull($transfer);

        $this->assertSame(
            'received',
            $transfer->status
        );

        $this->assertSame(
            $user->id,
            $transfer->received_by
        );

        $this->assertNotNull(
            $transfer->received_at
        );

        /*
         * Destination layer created from the breakdown.
         */
        $destinationLayer = DB::table('stock_layers')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->where('source_type', 'stock_transfer')
            ->where('source_id', $transferId)
            ->first();

        $this->assertNotNull($destinationLayer);

        $this->assertSame(
            5,
            (int) $destinationLayer->qty_remaining
        );

        $this->assertSame(
            10000,
            (int) $destinationLayer->unit_cost
        );

        /*
         * Source layer should still retain its reduced remaining qty.
         */
        $sourceLayer = DB::table('stock_layers')
            ->where('id', $layerId)
            ->first();

        $this->assertSame(
            15,
            (int) $sourceLayer->qty_remaining
        );

        /*
         * transfer_in movement recorded for audit trail.
         */
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $branchBWarehouseId,
            'product_variant_id' => $variant1Id,
            'movement_type' => 'transfer_in',
            'qty' => 5,
            'unit_cost' => 10000,
            'stock_layer_id' => $destinationLayer->id,
            'reference_type' => \Modules\Warehouse\Models\StockTransfer::class,
            'reference_id' => $transferId,
        ]);

        tenancy()->end();
    }

    /**
     * Receiving preserves FIFO breakdown unit costs when a transfer
     * was shipped from multiple layers.
     */
    public function test_receive_preserves_fifo_breakdown_costs(): void
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
         * Two layers (FIFO):
         * Layer 1: 10 @ 9000
         * Layer 2: 10 @ 11000
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

        DB::table('stock_balances')->insert([
            'product_variant_id' => $variant1Id,
            'warehouse_id' => $hqWarehouseId,
            'qty_on_hand' => 20,
        ]);

        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'FIFO receive test request',
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

        $transferItemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variant1Id,
            'qty' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $this->actingAs($user)
            ->post(
                route(
                    'warehouse.stock-transfers.ship',
                    $transferId
                )
            )
            ->assertRedirect(
                route(
                    'warehouse.stock-transfers.show',
                    $transferId
                )
            );

        tenancy()->initialize($tenantId);

        /*
         * FIFO should consume Layer1 -> 10, Layer2 -> 5.
         */
        $breakdown = DB::table('stock_transfer_item_layers')
            ->where('stock_transfer_item_id', $transferItemId)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $breakdown);

        $this->assertSame($layer1Id, (int) $breakdown[0]->stock_layer_id);
        $this->assertSame(10, (int) $breakdown[0]->qty_taken);
        $this->assertSame(9000, (int) $breakdown[0]->unit_cost);

        $this->assertSame($layer2Id, (int) $breakdown[1]->stock_layer_id);
        $this->assertSame(5, (int) $breakdown[1]->qty_taken);
        $this->assertSame(11000, (int) $breakdown[1]->unit_cost);

        tenancy()->end();

        $response = $this->actingAs($user)
            ->post(
                route(
                    'warehouse.stock-transfers.receive',
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
         * Destination should have two new layers preserving costs.
         */
        $destinationLayers = DB::table('stock_layers')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->where('source_type', 'stock_transfer')
            ->where('source_id', $transferId)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $destinationLayers);

        $this->assertSame(10, (int) $destinationLayers[0]->qty_remaining);
        $this->assertSame(9000, (int) $destinationLayers[0]->unit_cost);

        $this->assertSame(5, (int) $destinationLayers[1]->qty_remaining);
        $this->assertSame(11000, (int) $destinationLayers[1]->unit_cost);

        /*
         * Two transfer_in movements must exist.
         */
        $transferInCount = DB::table('stock_movements')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->where('movement_type', 'transfer_in')
            ->where('reference_type', \Modules\Warehouse\Models\StockTransfer::class)
            ->where('reference_id', $transferId)
            ->count();

        $this->assertSame(2, $transferInCount);

        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $branchBWarehouseId,
            'product_variant_id' => $variant1Id,
            'movement_type' => 'transfer_in',
            'qty' => 10,
            'unit_cost' => 9000,
            'stock_layer_id' => $destinationLayers[0]->id,
            'reference_type' => \Modules\Warehouse\Models\StockTransfer::class,
            'reference_id' => $transferId,
        ]);

        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $branchBWarehouseId,
            'product_variant_id' => $variant1Id,
            'movement_type' => 'transfer_in',
            'qty' => 5,
            'unit_cost' => 11000,
            'stock_layer_id' => $destinationLayers[1]->id,
            'reference_type' => \Modules\Warehouse\Models\StockTransfer::class,
            'reference_id' => $transferId,
        ]);

        tenancy()->end();
    }

    /**
     * A draft transfer cannot be received.
     */
    public function test_cannot_receive_draft_transfer(): void
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

        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'Draft receive test',
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
                'qty' => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        tenancy()->end();

        $response = $this->actingAs($user)
            ->from(
                route(
                    'warehouse.stock-transfers.show',
                    $transferId
                )
            )
            ->post(
                route(
                    'warehouse.stock-transfers.receive',
                    $transferId
                )
            );

        $response->assertRedirect(
            route(
                'warehouse.stock-transfers.show',
                $transferId
            )
        );

        $response->assertSessionHasErrors(
            'stock_transfer'
        );

        tenancy()->initialize($tenantId);

        $this->assertSame(
            'draft',
            DB::table('stock_transfers')
                ->where('id', $transferId)
                ->value('status')
        );

        tenancy()->end();
    }

    /**
     * An already received transfer cannot be received again.
     */
    public function test_cannot_receive_already_received_transfer(): void
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

        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'Already received test',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => $stockRequestId,
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $branchBWarehouseId,
            'status' => 'received',
            'shipped_by' => $user->id,
            'shipped_at' => now(),
            'received_by' => $user->id,
            'received_at' => now(),
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

        $response = $this->actingAs($user)
            ->from(
                route(
                    'warehouse.stock-transfers.show',
                    $transferId
                )
            )
            ->post(
                route(
                    'warehouse.stock-transfers.receive',
                    $transferId
                )
            );

        $response->assertRedirect(
            route(
                'warehouse.stock-transfers.show',
                $transferId
            )
        );

        $response->assertSessionHasErrors(
            'stock_transfer'
        );

        tenancy()->initialize($tenantId);

        $this->assertSame(
            'received',
            DB::table('stock_transfers')
                ->where('id', $transferId)
                ->value('status')
        );

        tenancy()->end();
    }

    /**
     * A user without transfer permission cannot receive a transfer.
     */
    public function test_user_without_transfer_permission_cannot_receive(): void
    {
        [$tenantId, $hqBranchId, $branchBId, $user] =
            $this->createCompanyWithMemberAndBranches();

        $branchOnlyUser = User::factory()->create([
            'email' => 'receive_no_perm_' . uniqid() . '@acme.test',
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

        $stockRequestId = DB::table('stock_requests')->insertGetId([
            'requesting_warehouse_id' => $branchBWarehouseId,
            'destination_warehouse_id' => $hqWarehouseId,
            'requested_by' => $user->id,
            'status' => 'approved',
            'note' => 'Permission receive test',
            'requested_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transferId = DB::table('stock_transfers')->insertGetId([
            'stock_request_id' => $stockRequestId,
            'from_warehouse_id' => $hqWarehouseId,
            'to_warehouse_id' => $branchBWarehouseId,
            'status' => 'shipped',
            'shipped_by' => $user->id,
            'shipped_at' => now(),
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

        $response = $this->actingAs($branchOnlyUser)
            ->post(
                route(
                    'warehouse.stock-transfers.receive',
                    $transferId
                )
            );

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
        $id = uniqid('recv_');
        $schemaName = 'sch_' . $id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Receive Test Corp',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        Artisan::call('tenants:migrate', [
            '--tenants' => [$tenant->id],
        ]);

        tenancy()->initialize($tenant);

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

        $branchBId = DB::table('branches')->insertGetId([
            'name' => 'Branch B',
            'code' => 'BRB_' . uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

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
        $hqWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-HQ-' . uniqid(),
            'name' => 'HQ Central Warehouse',
            'warehouse_type' => 'general',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-BRB-' . uniqid(),
            'name' => 'Branch B Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $catId = DB::table('product_categories')->insertGetId([
            'name' => 'Category ' . uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $uomId = DB::table('uoms')->insertGetId([
            'name' => 'PCS ' . uniqid(),
            'code' => 'PCS' . uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'code' => 'PRD-' . uniqid(),
            'name' => 'Widget ' . uniqid(),
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

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