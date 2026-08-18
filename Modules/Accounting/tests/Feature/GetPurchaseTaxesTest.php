<?php

namespace Modules\Accounting\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Accounting\Application\GetPurchaseTaxes;
use Modules\Accounting\Models\Tax;
use Modules\Company\Models\CompanyUser;
use Modules\Company\Tests\Support\CompanyTestFixture;
use Tests\TestCase;

class GetPurchaseTaxesTest extends TestCase
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

    public function test_returns_active_taxes_without_exposing_account_internals(): void
    {
        [$tenantId, $branchId] = $this->createCompanyWithMember();

        tenancy()->initialize($tenantId);

        Tax::create([
            'name' => 'PPN',
            'code' => 'PPN',
            'rate' => 11.0000,
            'input_account_id' => null,
            'output_account_id' => null,
            'is_active' => true,
        ]);

        Tax::create([
            'name' => 'Non Aktif',
            'code' => 'NONACT',
            'rate' => 5.0000,
            'is_active' => false,
        ]);

        $result = app(GetPurchaseTaxes::class)->execute();

        $this->assertCount(1, $result);
        $this->assertSame('PPN', $result[0]['name']);
        $this->assertSame('PPN', $result[0]['code']);
        $this->assertSame(11.0, $result[0]['rate']);

        foreach ($result as $tax) {
            $this->assertArrayNotHasKey('input_account_id', $tax);
            $this->assertArrayNotHasKey('output_account_id', $tax);
        }

        tenancy()->end();
    }

    /**
     * @return array{0: string|int, 1: int}
     */
    private function createCompanyWithMember(): array
    {
        $id = uniqid('tax_');
        $schemaName = 'sch_'.$id;

        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Accounting Tax Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $branchId = CompanyTestFixture::branch($tenant, [
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
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

        return [$tenant->id, (int) $branchId];
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
