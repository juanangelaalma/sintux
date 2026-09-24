<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\Purchasing\Application\SupplierMemo\ApplyDebitMemo;
use Modules\Purchasing\Application\SupplierMemo\ListSupplierDebitMemos;
use Modules\Purchasing\Models\SupplierDebitMemo;
use Tests\TestCase;

class SupplierDebitMemoTest extends TestCase
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

    public function test_list_returns_only_supplier_memos_with_remaining(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $this->insertMemo($ctx, 'DM/A/001', $ctx['supplierId'], 100000, 100000);
        $this->insertMemo($ctx, 'DM/A/002', $ctx['supplierId'], 50000, 0);
        $this->insertMemo($ctx, 'DM/A/003', $ctx['otherSupplierId'], 70000, 70000);

        $memos = app(ListSupplierDebitMemos::class)->execute(
            $ctx['supplierId'],
            [$ctx['hqBranchId']]
        );

        $this->assertCount(1, $memos);
        $this->assertSame('DM/A/001', $memos[0]['number']);
        $this->assertSame(100000.0, (float) $memos[0]['remaining']);

        tenancy()->end();
    }

    public function test_apply_reduces_remaining_and_closes_when_zero(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $memoId = $this->insertMemo($ctx, 'DM/B/001', $ctx['supplierId'], 100000, 100000);

        $memo = app(ApplyDebitMemo::class)->execute($memoId, 40000);

        $this->assertEquals(60000, (float) $memo->remaining);
        $this->assertSame('open', $memo->status);

        $memo = app(ApplyDebitMemo::class)->execute($memoId, 60000);

        $this->assertEquals(0, (float) $memo->remaining);
        $this->assertSame('applied', $memo->status);

        tenancy()->end();
    }

    public function test_apply_above_remaining_is_rejected_without_change(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $memoId = $this->insertMemo($ctx, 'DM/C/001', $ctx['supplierId'], 100000, 100000);

        try {
            app(ApplyDebitMemo::class)->execute($memoId, 150000);
            $this->fail('Expected ValidationException for over-apply.');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->errors());
        }

        $memo = SupplierDebitMemo::find($memoId);
        $this->assertEquals(100000, (float) $memo->remaining);
        $this->assertSame('open', $memo->status);

        tenancy()->end();
    }

    public function test_apply_is_idempotent_per_reference(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $memoId = $this->insertMemo($ctx, 'DM/D/001', $ctx['supplierId'], 100000, 100000);

        app(ApplyDebitMemo::class)->execute($memoId, 30000, 'purchase_payment', 501);
        app(ApplyDebitMemo::class)->execute($memoId, 30000, 'purchase_payment', 501);

        $memo = SupplierDebitMemo::find($memoId);
        $this->assertEquals(70000, (float) $memo->remaining);

        tenancy()->end();
    }

    private function insertMemo(array $ctx, string $number, int $supplierId, float $total, float $remaining): int
    {
        return DB::table('supplier_debit_memos')->insertGetId([
            'number' => $number,
            'branch_id' => $ctx['hqBranchId'],
            'supplier_id' => $supplierId,
            'status' => $remaining > 0 ? 'open' : 'applied',
            'total' => $total,
            'remaining' => $remaining,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('memo_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Memo Test-'.$id,
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

        $supplierId = DB::table('contacts')->insertGetId([
            'branch_id' => $hqBranchId,
            'type' => 'supplier',
            'name' => 'Supplier '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $otherSupplierId = DB::table('contacts')->insertGetId([
            'branch_id' => $hqBranchId,
            'type' => 'supplier',
            'name' => 'Supplier Lain '.uniqid(),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        return [
            'tenantId' => $tenant->id,
            'hqBranchId' => (int) $hqBranchId,
            'supplierId' => (int) $supplierId,
            'otherSupplierId' => (int) $otherSupplierId,
        ];
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
