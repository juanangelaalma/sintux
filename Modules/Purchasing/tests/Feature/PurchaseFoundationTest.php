<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Company\Database\Seeders\RolePermissionSeeder;
use Modules\Company\Models\CompanyUser;
use Modules\Company\Models\Permission;
use Modules\Company\Models\Role;
use Modules\Purchasing\Models\PurchaseRequest;
use Tests\TestCase;

class PurchaseFoundationTest extends TestCase
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

        parent::tearDown();
    }

    public function test_purchasing_tables_are_migrated_into_tenant_schema(): void
    {
        [$tenantId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $tables = [
            'purchase_requests',
            'purchase_request_items',
            'purchase_quotes',
            'purchase_quote_items',
            'purchase_orders',
            'purchase_order_items',
            'goods_receipts',
            'goods_receipt_items',
            'purchase_invoices',
            'purchase_invoice_items',
            'join_purchase_invoices',
            'join_purchase_invoice_items',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected tenant table [{$table}] to exist."
            );
        }

        tenancy()->end();
    }

    public function test_purchase_request_model_can_persist_into_tenant_schema(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        $request = PurchaseRequest::create([
            'number' => 'PR-HQ-0001',
            'branch_id' => $branchId,
            'status' => 'draft',
            'request_date' => now()->toDateString(),
            'currency_code' => 'IDR',
        ]);

        $this->assertSame('PR-HQ-0001', $request->number);
        $this->assertSame('draft', $request->status);

        $persisted = DB::table('purchase_requests')->where('id', $request->id)->first();
        $this->assertNotNull($persisted);
        $this->assertSame('IDR', (string) $persisted->currency_code);

        tenancy()->end();
    }

    public function test_role_permission_seeder_registers_purchasing_role_and_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $role = Role::where('slug', 'purchasing')->first();
        $this->assertNotNull($role, 'Purchasing role should be registered.');
        $this->assertSame('branch', $role->level);

        $permissionSlugs = [
            'purchasing.request.view',
            'purchasing.request.create',
            'purchasing.request.update',
            'purchasing.request.approve',
            'purchasing.quote.view',
            'purchasing.quote.create',
            'purchasing.quote.update',
            'purchasing.quote.send',
            'purchasing.po.view',
            'purchasing.po.create',
            'purchasing.po.update',
            'purchasing.po.approve',
            'purchasing.po.send',
            'purchasing.grn.view',
            'purchasing.grn.create',
            'purchasing.grn.post',
            'purchasing.invoice.view',
            'purchasing.invoice.create',
            'purchasing.invoice.approve',
            'purchasing.invoice.join',
        ];

        $count = Permission::whereIn('slug', $permissionSlugs)->count();
        $this->assertSame(count($permissionSlugs), $count);

        $role = $role->fresh();
        $this->assertTrue(
            $role->permissions->pluck('slug')->contains('purchasing.po.view')
        );
    }

    /**
     * @return array{0: string|int, 1: int, 2: User}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('pur_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Purchasing Test Corp-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

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

            DB::statement(
                'DROP SCHEMA IF EXISTS "'.$safeSchemaName.'" CASCADE'
            );
        } catch (\Throwable $e) {
        }
    }

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

                if (str_starts_with($schemaName, 'sch_')) {
                    $this->dropSchema($schemaName);
                }
            }
        } catch (\Throwable $e) {
        }
    }
}
