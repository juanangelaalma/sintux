<?php

namespace Modules\Warehouse\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class WarehouseCrudTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (tenancy()->initialized) {
            tenancy()->end();
        }

        /*
         * Remove tenant schemas left behind by previous
         * failed/interrupted tests.
         */
        $this->dropLeftoverSchemas();

        /*
         * Clean central tables in FK-safe order.
         *
         * stock_requests can reference users, so it must
         * be removed before users if it exists in the
         * current/public schema.
         */
        $this->cleanupCentralTables();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        /*
         * Drop the tenant schema created by this test.
         */
        if ($this->activeSchemaName) {
            $this->dropSchema($this->activeSchemaName);
            $this->activeSchemaName = null;
        }

        parent::tearDown();
    }

    public function test_company_member_can_manage_warehouses(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchId,
        ]);

        $suffix = uniqid();

        /*
         * CREATE
         */
        $this->actingAs($user)
            ->get(route('warehouse.warehouses.index'))
            ->assertStatus(200);

        $createCode = 'WH-HQ-'.$suffix;

        $this->actingAs($user)
            ->post(route('warehouse.warehouses.store'), [
                'branch_id' => $branchId,
                'code' => $createCode,
                'name' => 'Gudang Utama-'.$suffix,
                'warehouse_type' => 'regular',
                'address' => 'Jl. Sudirman',
                'is_active' => true,
            ])
            ->assertRedirect(route('warehouse.warehouses.index'));

        tenancy()->initialize($tenantId);

        $warehouse = DB::table('warehouses')
            ->where('code', $createCode)
            ->first();

        $this->assertNotNull($warehouse);
        $this->assertSame(
            'Gudang Utama-'.$suffix,
            $warehouse->name
        );
        $this->assertSame(
            'regular',
            $warehouse->warehouse_type
        );

        tenancy()->end();

        /*
         * UPDATE
         */
        $updateCode = 'WH-HQ-UPD-'.$suffix;

        $this->actingAs($user)
            ->put(
                route(
                    'warehouse.warehouses.update',
                    ['warehouse' => $warehouse->id]
                ),
                [
                    'branch_id' => $branchId,
                    'code' => $updateCode,
                    'name' => 'Gudang Cadangan-'.$suffix,
                    'warehouse_type' => 'retail',
                    'address' => 'Jl. Thamrin',
                    'is_active' => false,
                ]
            )
            ->assertRedirect(route('warehouse.warehouses.index'));

        tenancy()->initialize($tenantId);

        $updated = DB::table('warehouses')
            ->where('id', $warehouse->id)
            ->first();

        $this->assertNotNull($updated);
        $this->assertSame($updateCode, $updated->code);
        $this->assertSame(
            'Gudang Cadangan-'.$suffix,
            $updated->name
        );
        $this->assertSame(
            'retail',
            $updated->warehouse_type
        );
        $this->assertFalse((bool) $updated->is_active);

        tenancy()->end();

        /*
         * DELETE
         */
        $this->actingAs($user)
            ->delete(
                route(
                    'warehouse.warehouses.destroy',
                    ['warehouse' => $warehouse->id]
                )
            )
            ->assertRedirect();

        tenancy()->initialize($tenantId);

        $this->assertDatabaseMissing(
            'warehouses',
            ['id' => $warehouse->id]
        );

        tenancy()->end();
    }

    public function test_validation_rejects_duplicate_code_and_invalid_type(): void
    {
        [$tenantId, $branchId, $user] = $this->createCompanyWithMember();

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $branchId,
        ]);

        $suffix = uniqid();
        $duplicateCode = 'WH-DUP-'.$suffix;

        /*
         * Create the first warehouse.
         */
        $this->actingAs($user)
            ->post(route('warehouse.warehouses.store'), [
                'branch_id' => $branchId,
                'code' => $duplicateCode,
                'name' => 'Gudang 1-'.$suffix,
                'warehouse_type' => 'regular',
            ])
            ->assertRedirect();

        /*
         * Try to create another warehouse with the same
         * code and an invalid warehouse type.
         */
        $this->actingAs($user)
            ->post(route('warehouse.warehouses.store'), [
                'branch_id' => $branchId,
                'code' => $duplicateCode,
                'name' => 'Gudang 2-'.$suffix,
                'warehouse_type' => 'bunker',
            ])
            ->assertSessionHasErrors([
                'code',
                'warehouse_type',
            ]);
    }

    public function test_list_is_scoped_to_accessible_branch(): void
    {
        [$tenantId, $defaultBranchId, $user] = $this->createCompanyWithMember();

        $suffix = uniqid();

        tenancy()->initialize($tenantId);

        $nonHqBranchId = DB::table('branches')->insertGetId([
            'name' => 'Non HQ Branch-'.$suffix,
            'code' => 'BR-NONHQ-'.$suffix,
            'is_headquarters' => false,
            'is_active' => true,
        ]);

        $companyUser = CompanyUser::where('user_id', $user->id)->where('tenant_id', $tenantId)->first();
        tenancy()->end();
        DB::table('company_user_branches')
            ->where('company_user_id', $companyUser->id)
            ->update(['branch_id' => $nonHqBranchId]);
        tenancy()->initialize($tenantId);

        session([
            'active_tenant_id' => $tenantId,
            'active_branch_id' => $nonHqBranchId,
        ]);

        /*
         * Create another branch that the current user
         * does not have access to.
         */
        $otherBranchId = DB::table('branches')->insertGetId([
            'name' => 'Other Branch-'.$suffix,
            'code' => 'BR-OTHER-'.$suffix,
            'is_headquarters' => false,
            'is_active' => true,
        ]);

        $ownWarehouseCode = 'WH-OWN-'.$suffix;
        $otherWarehouseCode = 'WH-OTHER-'.$suffix;

        DB::table('warehouses')->insert([
            [
                'branch_id' => $nonHqBranchId,
                'code' => $ownWarehouseCode,
                'name' => 'Own Warehouse-'.$suffix,
                'warehouse_type' => 'regular',
                'is_active' => true,
            ],
            [
                'branch_id' => $otherBranchId,
                'code' => $otherWarehouseCode,
                'name' => 'Other Warehouse-'.$suffix,
                'warehouse_type' => 'regular',
                'is_active' => true,
            ],
        ]);

        tenancy()->end();

        $response = $this->actingAs($user)
            ->get(route('warehouse.warehouses.index'));

        $response
            ->assertStatus(200)
            ->assertInertia(
                fn ($page) => $page
                    ->component('Warehouse/Warehouses/index')
                    ->has('warehouses.data', 1)
                    ->has(
                        'warehouses.data.0',
                        fn ($warehouse) => $warehouse
                            ->where('code', $ownWarehouseCode)
                            ->etc()
                    )
            );
    }

    /**
     * @return array{0: string, 1: int, 2: User}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('wh_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Warehouse Test Corp-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        /*
         * Initialize tenant so the tenant schema and
         * its migrations/tables are available.
         */
        tenancy()->initialize($tenant);

        $existingHq = DB::table('branches')
            ->where('code', 'HQ')
            ->value('id');

        $branchId = $existingHq
            ? (int) $existingHq
            : DB::table('branches')->insertGetId([
                'name' => 'HQ Branch',
                'code' => 'HQ',
                'is_headquarters' => true,
                'is_active' => true,
            ]);

        tenancy()->end();

        /*
         * User belongs to central database.
         */
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
            'branch_id' => $branchId,
        ]);

        return [
            $tenant->id,
            (int) $branchId,
            $user,
        ];
    }

    /**
     * Clean central/public tables while respecting
     * foreign key dependencies.
     */
    private function cleanupCentralTables(): void
    {
        /*
         * Child tables first.
         */
        $this->deleteIfTableExists('stock_request_items');
        $this->deleteIfTableExists('stock_requests');

        $this->deleteIfTableExists('company_user_branches');
        $this->deleteIfTableExists('company_users');

        /*
         * Tenant records may be referenced by company_users.
         */
        $this->deleteIfTableExists('tenants');

        /*
         * Users must be deleted after every table that
         * references users.id.
         */
        $this->deleteIfTableExists('users');
    }

    /**
     * Delete all rows only if the table exists in
     * the current PostgreSQL schema.
     */
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

    /**
     * Drop a tenant schema safely.
     */
    private function dropSchema(string $schemaName): void
    {
        try {
            $safeSchemaName = str_replace('"', '""', $schemaName);

            DB::statement(
                'DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE'
            );
        } catch (\Throwable $e) {
            /*
             * Cleanup failure should not hide the actual
             * test failure.
             */
        }
    }

    /**
     * Drop leftover test tenant schemas.
     */
    private function dropLeftoverSchemas(): void
    {
        try {
            $schemas = DB::select(
                "SELECT schema_name
                 FROM information_schema.schemata
                 WHERE schema_name NOT IN (
                     'public',
                     'information_schema'
                 )
                 AND schema_name NOT LIKE 'pg_%'"
            );

            foreach ($schemas as $row) {
                $schemaName = $row->schema_name;

                /*
                 * Only delete schemas generated by tests.
                 */
                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Throwable $e) {
            /*
             * Ignore cleanup errors.
             */
        }
    }
}
