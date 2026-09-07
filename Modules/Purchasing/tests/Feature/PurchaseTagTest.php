<?php

namespace Modules\Purchasing\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Tests\TestCase;

class PurchaseTagTest extends TestCase
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

    public function test_member_can_create_tag_and_duplicates_are_deduped(): void
    {
        [$tenantId, $hqBranchId, $user] = $this->createCompanyWithMember();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        tenancy()->end();

        $first = $this->actingAs($user)
            ->postJson(route('purchasing.tags.store'), ['name' => '  Kilat  ']);

        $first->assertCreated()
            ->assertJsonStructure(['id', 'name', 'color']);
        $this->assertSame('Kilat', $first->json('name'));

        $second = $this->actingAs($user)
            ->postJson(route('purchasing.tags.store'), ['name' => 'Kilat']);

        $second->assertCreated();
        $this->assertSame($first->json('id'), $second->json('id'));

        tenancy()->initialize($tenantId);
        $this->assertSame(1, (int) DB::table('purchase_tags')->where('name', 'Kilat')->count());
        tenancy()->end();
    }

    public function test_validation_rejects_missing_or_too_long_name(): void
    {
        [$tenantId, $hqBranchId, $user] = $this->createCompanyWithMember();
        session(['active_tenant_id' => $tenantId, 'active_branch_id' => $hqBranchId]);
        tenancy()->initialize($tenantId);
        tenancy()->end();

        $this->actingAs($user)
            ->postJson(route('purchasing.tags.store'), [])
            ->assertStatus(422);

        $this->actingAs($user)
            ->postJson(route('purchasing.tags.store'), ['name' => str_repeat('x', 51)])
            ->assertStatus(422);
    }

    /**
     * @return array{0: string|int, 1: int, 2: User}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('ptag_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Tag Test-'.$id,
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

    private function cleanupCentralTables(): void
    {
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
