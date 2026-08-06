<?php

namespace Modules\Company\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Modules\Company\Application\CreateCompany;
use Modules\Company\Application\Exceptions\CompanyCreationFailed;
use Tests\TestCase;

class CreateCompanyTest extends TestCase
{
    private const SCHEMA_NAME = 'company_fail_cleanup';

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('company_users')->delete();
        DB::table('tenants')->delete();
        DB::table('users')->delete();

        $this->dropSchema();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        $this->dropSchema();
        parent::tearDown();
    }

    public function test_creation_failure_cleans_up_tenant_row_and_schema(): void
    {
        $this->expectException(CompanyCreationFailed::class);

        try {
            (new CreateCompany)->execute([
                'id' => 'fail-tenant',
                'name' => 'Fail Corp',
                'schema_name' => self::SCHEMA_NAME,
                'is_active' => true,
                'admin_name' => 'Fail Admin',
                'admin_email' => 'fail-admin@sintux.com',
                'admin_password' => 'secret-password',
            ]);
        } finally {
            $this->assertDatabaseMissing('tenants', ['id' => 'fail-tenant']);
            $this->assertDatabaseMissing('users', ['email' => 'fail-admin@sintux.com']);
            $this->assertFalse($this->schemaExists(), 'Tenant schema should have been dropped on failure.');
        }
    }

    private function createSchema(): void
    {
        try {
            $this->pdo()->exec('CREATE SCHEMA "'.self::SCHEMA_NAME.'"');
        } catch (\Exception $e) {
        }
    }

    private function dropSchema(): void
    {
        try {
            $this->pdo()->exec('DROP SCHEMA IF EXISTS "'.self::SCHEMA_NAME.'" CASCADE');
        } catch (\Exception $e) {
        }
    }

    private function schemaExists(): bool
    {
        try {
            return (bool) $this->pdo()
                ->query("SELECT 1 FROM information_schema.schemata WHERE schema_name = '".self::SCHEMA_NAME."'")
                ->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function pdo(): \PDO
    {
        return new \PDO(
            'pgsql:host=127.0.0.1;port=5432;dbname=testing;user=root;password='
        );
    }
}
