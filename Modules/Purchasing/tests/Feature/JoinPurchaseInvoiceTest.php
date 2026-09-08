<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Purchasing\Enums\JoinPurchaseInvoiceStatus;
use Modules\Purchasing\Enums\PurchaseInvoiceStatus;
use Modules\Purchasing\Models\JoinPurchaseInvoice;
use Modules\Purchasing\Models\PurchaseInvoice;
use Tests\TestCase;

class JoinPurchaseInvoiceTest extends TestCase
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

    public function test_member_can_create_join_purchase_invoice(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        $supplierId = $this->createSupplier($branchBId);
        $supplierName = DB::table('contacts')->where('id', $supplierId)->value('name');

        $inv1 = PurchaseInvoice::create([
            'number' => 'INV-BRB-0001',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => PurchaseInvoiceStatus::Approved,
            'invoice_date' => '2026-08-18',
            'total' => 500000,
        ]);
        $inv2 = PurchaseInvoice::create([
            'number' => 'INV-BRB-0002',
            'branch_id' => $branchBId,
            'supplier_id' => $supplierId,
            'status' => PurchaseInvoiceStatus::Approved,
            'invoice_date' => '2026-08-18',
            'total' => 300000,
        ]);
        $branchCode = DB::table('branches')->where('id', $branchBId)->value('code');
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.joins.store'), [
            'branch_id' => $branchBId,
            'join_date' => '2026-08-18',
            'note' => 'Konsolidasi Tagihan Agustus',
            'items' => [
                [
                    'purchase_invoice_id' => $inv1->id,
                    'supplier_id' => $supplierId,
                    'invoice_number' => $inv1->number,
                    'supplier_name' => (string) $supplierName,
                    'invoice_total' => 500000,
                ],
                [
                    'purchase_invoice_id' => $inv2->id,
                    'supplier_id' => $supplierId,
                    'invoice_number' => $inv2->number,
                    'supplier_name' => (string) $supplierName,
                    'invoice_total' => 300000,
                ],
            ],
        ]);

        $response->assertRedirect(route('purchasing.joins.index'));

        tenancy()->initialize($tenantId);
        $join = DB::table('join_purchase_invoices')->where('branch_id', $branchBId)->first();
        $this->assertNotNull($join);
        $this->assertSame(JoinPurchaseInvoiceStatus::Draft->value, $join->status);
        $this->assertSame('JOIN-HQ-0001', (string) $join->number);
        $this->assertEquals(800000, (float) $join->total_amount);

        $items = DB::table('join_purchase_invoice_items')->where('join_purchase_invoice_id', $join->id)->get();
        $this->assertCount(2, $items);
        tenancy()->end();
    }

    public function test_ready_join_invoice_transitions_draft_to_ready(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);

        $join = JoinPurchaseInvoice::create([
            'number' => 'JOIN-BRB-0002',
            'branch_id' => $branchBId,
            'status' => JoinPurchaseInvoiceStatus::Draft,
            'join_date' => now()->toDateString(),
            'total_amount' => 800000,
        ]);
        $joinId = $join->id;
        tenancy()->end();

        $response = $this->actingAs($user)->post(route('purchasing.joins.ready', $joinId));
        $response->assertRedirect(route('purchasing.joins.show', $joinId));

        tenancy()->initialize($tenantId);
        $this->assertSame(JoinPurchaseInvoiceStatus::Ready->value, DB::table('join_purchase_invoices')->where('id', $joinId)->value('status'));
        tenancy()->end();
    }

    /**
     * @return array{0: string|int, 1: int, 2: int, 3: User}
     */
    private function createCompanyWithMemberAndBranches(): array
    {
        $id = uniqid('join_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Join Invoice Test-'.$id,
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

        return [$tenant->id, (int) $branchBId, (int) $hqBranchId, $user];
    }

    public function test_non_hq_branch_is_forbidden_from_join_purchase_invoices(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchBId]);

        $this->actingAs($user)->get(route('purchasing.joins.index'))->assertForbidden();
        $this->actingAs($user)->post(route('purchasing.joins.store'), [])->assertForbidden();
    }

    public function test_hq_branch_can_view_join_purchase_invoices_index(): void
    {
        [$tenantId, $branchBId, $hqBranchId, $user] = $this->createCompanyWithMemberAndBranches();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);

        $this->actingAs($user)->get(route('purchasing.joins.index'))->assertOk();
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
        $this->deleteIfTableExists('join_purchase_invoice_items');
        $this->deleteIfTableExists('join_purchase_invoices');
        $this->deleteIfTableExists('purchase_invoice_items');
        $this->deleteIfTableExists('purchase_invoices');
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
