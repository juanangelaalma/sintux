<?php

namespace Modules\Approval\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Approval\Application\ApprovalEngine;
use Modules\Approval\Application\CreateApprovalRule;
use Modules\Approval\Application\DeleteApprovalRule;
use Modules\Approval\Application\PerformApprovalAction;
use Modules\Approval\Models\ApprovalMapping;
use Modules\Approval\Models\ApprovalRule;
use Modules\Approval\Models\ApprovalTransactionType;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class ApprovalRuleTest extends TestCase
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

    public function test_can_create_approval_rule_and_evaluate_engine(): void
    {
        [$tenantId, $branchId, $owner, $approver1, $approver2] = $this->createCompanyWithUsers();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);
        tenancy()->initialize($tenantId);

        $type = ApprovalTransactionType::where('key', 'purchase_order')->firstOrFail();

        $createRule = app(CreateApprovalRule::class);
        $rule = $createRule->execute([
            'transaction_type_id' => $type->id,
            'name' => 'PO Nominal > 1Jt',
            'min_amount' => 1000000,
            'currency_code' => 'IDR',
            'scope_all_users' => true,
            'apply_to_existing_draft' => true,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$approver1->id, $approver2->id]],
            ],
        ], $owner->id);

        $this->assertInstanceOf(ApprovalRule::class, $rule);
        $this->assertSame('PO Nominal > 1Jt', $rule->name);

        $engine = app(ApprovalEngine::class);

        // Facts below min_amount -> no mapping (null)
        $noMatch = $engine->evaluateAndMap([
            'transaction_type' => 'purchase_order',
            'transaction_id' => 101,
            'document_number' => 'PO-001',
            'created_by' => $owner->id,
            'total' => 500000,
            'currency_code' => 'IDR',
        ]);
        $this->assertNull($noMatch);

        // Facts above min_amount -> mapping created
        $mapping = $engine->evaluateAndMap([
            'transaction_type' => 'purchase_order',
            'transaction_id' => 102,
            'document_number' => 'PO-002',
            'created_by' => $owner->id,
            'total' => 2000000,
            'currency_code' => 'IDR',
        ]);

        $this->assertInstanceOf(ApprovalMapping::class, $mapping);
        $this->assertSame('pending', $mapping->overall_status);
        $this->assertSame(1, $mapping->current_stage_order);
    }

    public function test_approval_action_progresses_stage_and_finalizes(): void
    {
        [$tenantId, $branchId, $owner, $approver1, $approver2] = $this->createCompanyWithUsers();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);
        tenancy()->initialize($tenantId);

        $type = ApprovalTransactionType::where('key', 'purchase_order')->firstOrFail();

        $rule = app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $type->id,
            'name' => 'PO Multi Stage',
            'min_amount' => 1000000,
            'currency_code' => 'IDR',
            'scope_all_users' => true,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$approver1->id]],
                ['approval_type' => 'all', 'approver_ids' => [$approver2->id]],
            ],
        ], $owner->id);

        $mapping = app(ApprovalEngine::class)->evaluateAndMap([
            'transaction_type' => 'purchase_order',
            'transaction_id' => 201,
            'document_number' => 'PO-201',
            'created_by' => $owner->id,
            'total' => 5000000,
            'currency_code' => 'IDR',
        ]);

        $this->assertNotNull($mapping);
        $this->assertSame(1, $mapping->current_stage_order);

        // Approver 1 approves Stage 1 ('any' type -> stage completes, advances to Stage 2)
        $performAction = app(PerformApprovalAction::class);
        $mapping = $performAction->execute($mapping->id, $approver1->id, 'approve', 'OK Stage 1');

        $this->assertSame(2, $mapping->current_stage_order);
        $this->assertSame('pending', $mapping->overall_status);

        // Approver 2 approves Stage 2 ('all' type -> workflow approved)
        $mapping = $performAction->execute($mapping->id, $approver2->id, 'approve', 'OK Final');

        $this->assertSame('approved', $mapping->overall_status);
    }

    public function test_reject_action_stops_workflow(): void
    {
        [$tenantId, $branchId, $owner, $approver1, $approver2] = $this->createCompanyWithUsers();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);
        tenancy()->initialize($tenantId);

        $type = ApprovalTransactionType::where('key', 'purchase_order')->firstOrFail();

        app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $type->id,
            'name' => 'PO Rule',
            'min_amount' => 1000000,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$approver1->id]],
            ],
        ], $owner->id);

        $mapping = app(ApprovalEngine::class)->evaluateAndMap([
            'transaction_type' => 'purchase_order',
            'transaction_id' => 301,
            'document_number' => 'PO-301',
            'created_by' => $owner->id,
            'total' => 2000000,
        ]);

        $performAction = app(PerformApprovalAction::class);
        $mapping = $performAction->execute($mapping->id, $approver1->id, 'reject', 'Harga Terlalu Mahal');

        $this->assertSame('rejected', $mapping->overall_status);
    }

    public function test_cannot_delete_rule_with_pending_mappings(): void
    {
        [$tenantId, $branchId, $owner, $approver1] = $this->createCompanyWithUsers();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $branchId]);
        tenancy()->initialize($tenantId);

        $type = ApprovalTransactionType::where('key', 'purchase_order')->firstOrFail();

        $rule = app(CreateApprovalRule::class)->execute([
            'transaction_type_id' => $type->id,
            'name' => 'PO Active Rule',
            'min_amount' => 500000,
            'stages' => [
                ['approval_type' => 'any', 'approver_ids' => [$approver1->id]],
            ],
        ], $owner->id);

        app(ApprovalEngine::class)->evaluateAndMap([
            'transaction_type' => 'purchase_order',
            'transaction_id' => 401,
            'document_number' => 'PO-401',
            'created_by' => $owner->id,
            'total' => 1000000,
        ]);

        $this->expectException(ValidationException::class);
        app(DeleteApprovalRule::class)->execute($rule->id, $owner->id);
    }

    private function createCompanyWithUsers(): array
    {
        $tenantId = 'approval_test_'.uniqid();
        $schemaName = 'company_'.$tenantId;
        $this->activeSchemaName = $schemaName;

        Tenant::create([
            'id' => $tenantId,
            'name' => 'Approval Test Company',
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenantId);

        $branchId = (int) DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_active' => true,
            'is_headquarters' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $owner = User::create([
            'name' => 'Owner User',
            'email' => 'owner_'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
        ]);

        $approver1 = User::create([
            'name' => 'Approver One',
            'email' => 'approver1_'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
        ]);

        $approver2 = User::create([
            'name' => 'Approver Two',
            'email' => 'approver2_'.uniqid().'@test.com',
            'password' => bcrypt('password'),
            'role' => 'user',
        ]);

        CompanyUser::create(['user_id' => $owner->id, 'tenant_id' => $tenantId, 'branch_id' => $branchId, 'role' => 'owner', 'is_default' => true]);
        CompanyUser::create(['user_id' => $approver1->id, 'tenant_id' => $tenantId, 'branch_id' => $branchId, 'role' => 'member', 'is_default' => false]);
        CompanyUser::create(['user_id' => $approver2->id, 'tenant_id' => $tenantId, 'branch_id' => $branchId, 'role' => 'member', 'is_default' => false]);

        return [$tenantId, $branchId, $owner, $approver1, $approver2];
    }

    private function cleanupCentralTables(): void
    {
        DB::table('company_user_roles')->delete();
        DB::table('company_user_branches')->delete();
        DB::table('company_users')->delete();
        DB::table('users')->where('email', 'like', '%@test.com')->delete();
        DB::table('tenants')->where('id', 'like', 'approval_test_%')->delete();
    }

    private function dropLeftoverSchemas(): void
    {
        $schemas = DB::select("SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'company_approval_test_%'");
        foreach ($schemas as $s) {
            $this->dropSchema($s->schema_name);
        }
    }

    private function dropSchema(string $schemaName): void
    {
        DB::statement("DROP SCHEMA IF EXISTS \"{$schemaName}\" CASCADE");
    }
}
