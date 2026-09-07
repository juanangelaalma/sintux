<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
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

        DB::table('company_user_branches')->delete();
        DB::table('company_users')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();

        $this->seed(RolePermissionSeeder::class);
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
     * Normal receive: shipped 10, received 10.
     * Branch stock increases by 10.
     */
    public function test_receive_normal_full_qty(): void
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
            'note' => 'Receive test',
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
            'qty' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        /*
         * Ship first.
         */
        $this->actingAs($user)
            ->post(
                route('warehouse.stock-transfers.ship', $transferId)
            )
            ->assertRedirect(
                route('warehouse.stock-transfers.show', $transferId)
            );

        tenancy()->initialize($tenantId);

        /*
         * After ship: HQ stock decreased, destination NOT increased.
         */
        $hqStock = DB::table('stock_balances')
            ->where('warehouse_id', $hqWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNotNull($hqStock);
        $this->assertSame(10, (int) $hqStock->qty_on_hand);

        $destStock = DB::table('stock_balances')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNull($destStock);

        /*
         * qty_shipped is set.
         */
        $item = DB::table('stock_transfer_items')
            ->where('id', $transferItemId)
            ->first();

        $this->assertSame(10, (int) $item->qty_shipped);
        $this->assertSame(0, (int) $item->qty_received);

        tenancy()->end();

        /*
         * Receive full qty.
         */
        $response = $this->actingAs($user)
            ->post(
                route('warehouse.stock-transfers.receive', $transferId),
                [
                    'received_items' => [
                        [
                            'stock_transfer_item_id' => $transferItemId,
                            'qty_received' => 10,
                        ],
                    ],
                ]
            );

        $response->assertRedirect(
            route('warehouse.stock-transfers.show', $transferId)
        );

        tenancy()->initialize($tenantId);

        /*
         * Transfer status = received.
         */
        $transfer = DB::table('stock_transfers')
            ->where('id', $transferId)
            ->first();

        $this->assertSame('received', $transfer->status);

        /*
         * Destination stock increased by received qty.
         */
        $destStock = DB::table('stock_balances')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNotNull($destStock);
        $this->assertSame(10, (int) $destStock->qty_on_hand);

        /*
         * qty_received updated on item.
         */
        $item = DB::table('stock_transfer_items')
            ->where('id', $transferItemId)
            ->first();

        $this->assertSame(10, (int) $item->qty_received);

        /*
         * No discrepancy created.
         */
        $this->assertDatabaseMissing('stock_transfer_discrepancies', [
            'stock_transfer_id' => $transferId,
        ]);

        tenancy()->end();
    }

    /**
     * Partial receive: shipped 10, received 6.
     * Branch stock increases by 6. Transfer stays shipped.
     */
    public function test_receive_partial_qty(): void
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
            'note' => 'Partial receive test',
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
            'qty' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        /*
         * Ship.
         */
        $this->actingAs($user)
            ->post(
                route('warehouse.stock-transfers.ship', $transferId)
            );

        tenancy()->initialize($tenantId);

        tenancy()->end();

        /*
         * Receive partial (6 of 10).
         */
        $response = $this->actingAs($user)
            ->post(
                route('warehouse.stock-transfers.receive', $transferId),
                [
                    'received_items' => [
                        [
                            'stock_transfer_item_id' => $transferItemId,
                            'qty_received' => 6,
                        ],
                    ],
                ]
            );

        $response->assertRedirect(
            route('warehouse.stock-transfers.show', $transferId)
        );

        tenancy()->initialize($tenantId);

        /*
         * Transfer still shipped (not fully received).
         */
        $transfer = DB::table('stock_transfers')
            ->where('id', $transferId)
            ->first();

        $this->assertSame('shipped', $transfer->status);

        /*
         * Destination stock increased by 6.
         */
        $destStock = DB::table('stock_balances')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNotNull($destStock);
        $this->assertSame(6, (int) $destStock->qty_on_hand);

        /*
         * qty_received = 6.
         */
        $item = DB::table('stock_transfer_items')
            ->where('id', $transferItemId)
            ->first();

        $this->assertSame(6, (int) $item->qty_received);

        /*
         * Discrepancy created (10 shipped vs 6 received).
         */
        $this->assertDatabaseHas('stock_transfer_discrepancies', [
            'stock_transfer_id' => $transferId,
            'stock_transfer_item_id' => $transferItemId,
            'shipped_qty' => 10,
            'received_qty' => 6,
            'difference_qty' => 4,
            'status' => 'pending',
        ]);

        tenancy()->end();
    }

    /**
     * Receive more than shipped must be rejected.
     */
    public function test_cannot_receive_more_than_shipped(): void
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
            'note' => 'Over receive test',
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
         * Ship 5.
         */
        $this->actingAs($user)
            ->post(
                route('warehouse.stock-transfers.ship', $transferId)
            );

        tenancy()->initialize($tenantId);

        tenancy()->end();

        /*
         * Try to receive 10 (more than shipped 5).
         */
        $response = $this->actingAs($user)
            ->from(
                route('warehouse.stock-transfers.show', $transferId)
            )
            ->post(
                route('warehouse.stock-transfers.receive', $transferId),
                [
                    'received_items' => [
                        [
                            'stock_transfer_item_id' => $transferItemId,
                            'qty_received' => 10,
                        ],
                    ],
                ]
            );

        $response->assertRedirect();

        $response->assertSessionHasErrors('qty_received');

        tenancy()->initialize($tenantId);

        /*
         * Stock must not change.
         */
        $destStock = DB::table('stock_balances')
            ->where('warehouse_id', $branchBWarehouseId)
            ->where('product_variant_id', $variant1Id)
            ->first();

        $this->assertNull($destStock);

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

        $transferItemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variant1Id,
            'qty' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $response = $this->actingAs($user)
            ->from(
                route('warehouse.stock-transfers.show', $transferId)
            )
            ->post(
                route('warehouse.stock-transfers.receive', $transferId),
                [
                    'received_items' => [
                        [
                            'stock_transfer_item_id' => $transferItemId,
                            'qty_received' => 5,
                        ],
                    ],
                ]
            );

        $response->assertRedirect(
            route('warehouse.stock-transfers.show', $transferId)
        );

        $response->assertSessionHasErrors('stock_transfer');

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

        $transferItemId = DB::table('stock_transfer_items')->insertGetId([
            'stock_transfer_id' => $transferId,
            'product_variant_id' => $variant1Id,
            'qty' => 5,
            'qty_shipped' => 5,
            'qty_received' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $response = $this->actingAs($user)
            ->from(
                route('warehouse.stock-transfers.show', $transferId)
            )
            ->post(
                route('warehouse.stock-transfers.receive', $transferId),
                [
                    'received_items' => [
                        [
                            'stock_transfer_item_id' => $transferItemId,
                            'qty_received' => 5,
                        ],
                    ],
                ]
            );

        $response->assertRedirect(
            route('warehouse.stock-transfers.show', $transferId)
        );

        $response->assertSessionHasErrors('stock_transfer');

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
     * @return array{0: string, 1: int, 2: int, 3: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('recv_');
        $schemaName = 'sch_'.$id;

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
            'code' => 'BRB_'.uniqid(),
            'is_headquarters' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'owner_'.$id.'@acme.test',
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
     * @return array{0: int, 1: int, 2: int}
     */
    private function seedWarehouseAndVariants(
        int $hqBranchId,
        int $branchBId
    ): array {
        $hqWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $hqBranchId,
            'code' => 'WH-HQ-'.uniqid(),
            'name' => 'HQ Central Warehouse',
            'warehouse_type' => 'regular',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchBWarehouseId = DB::table('warehouses')->insertGetId([
            'branch_id' => $branchBId,
            'code' => 'WH-BRB-'.uniqid(),
            'name' => 'Branch B Warehouse',
            'warehouse_type' => 'regular',
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
            'name' => 'Widget '.uniqid(),
            'category_id' => $catId,
            'uom_id' => $uomId,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $variantId = DB::table('product_variants')->insertGetId([
            'branch_id' => $hqBranchId,
            'product_id' => $productId,
            'sku' => 'SKU-'.uniqid(),
            'variant_name' => 'Widget Variant '.uniqid(),
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
                'DROP SCHEMA IF EXISTS "'.
                $schemaName.
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
