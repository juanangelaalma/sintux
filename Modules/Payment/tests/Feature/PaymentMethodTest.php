<?php

namespace Modules\Payment\Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Company\Models\CompanyUser;
use Modules\Payment\Models\PaymentMethod;
use Tests\TestCase;

class PaymentMethodTest extends TestCase
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

    public function test_seed_creates_four_default_methods(): void
    {
        $ctx = $this->seedContext();
        tenancy()->initialize($ctx['tenantId']);

        $names = PaymentMethod::orderBy('name')->pluck('name')->all();

        $this->assertCount(4, $names);
        $this->assertContains('Kas Tunai', $names);
        $this->assertContains('Cek & Giro', $names);
        $this->assertContains('Transfer Bank', $names);
        $this->assertContains('Kartu Kredit', $names);

        tenancy()->end();
    }

    public function test_index_returns_active_methods(): void
    {
        $ctx = $this->seedContext();

        $response = $this->withSession([
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $ctx['branchId'],
        ])->actingAs($ctx['user'])->getJson(route('payment-methods.index'));

        $response->assertOk();
        $this->assertCount(4, $response->json());
    }

    public function test_store_creates_method(): void
    {
        $ctx = $this->seedContext();

        $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->postJson(route('payment-methods.store'), ['name' => 'QRIS'])
            ->assertCreated();

        tenancy()->initialize($ctx['tenantId']);
        $this->assertDatabaseHas('payment_methods', ['name' => 'QRIS']);
        tenancy()->end();
    }

    public function test_store_rejects_duplicate_name(): void
    {
        $ctx = $this->seedContext();

        // "Transfer Bank" sudah ada dari seed.
        $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->postJson(route('payment-methods.store'), ['name' => 'Transfer Bank'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_update_can_deactivate_method_without_deleting(): void
    {
        $ctx = $this->seedContext();

        tenancy()->initialize($ctx['tenantId']);
        $method = PaymentMethod::where('code', 'cash')->firstOrFail();
        $methodId = (int) $method->id;
        tenancy()->end();

        $this->withSession($this->tenantSession($ctx))
            ->actingAs($ctx['user'])
            ->patchJson(route('payment-methods.update', $methodId), [
                'name' => 'Kas Tunai',
                'is_active' => false,
            ])
            ->assertOk();

        tenancy()->initialize($ctx['tenantId']);
        $method->refresh();
        $this->assertFalse($method->is_active);
        $this->assertDatabaseHas('payment_methods', ['id' => $methodId, 'name' => 'Kas Tunai']);
        tenancy()->end();
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function tenantSession(array $ctx): array
    {
        return [
            'active_tenant_id' => $ctx['tenantId'],
            'active_branch_id' => $ctx['branchId'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seedContext(): array
    {
        $id = uniqid('pm_');
        $schemaName = 'sch_'.$id;
        $this->activeSchemaName = $schemaName;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Payment Method Test-'.$id,
            'schema_name' => $schemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);
        $this->artisan('tenants:migrate', ['--tenants' => [$tenant->id]]);

        $branchId = DB::table('branches')->insertGetId([
            'name' => 'HQ Branch',
            'code' => 'HQ',
            'is_headquarters' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        tenancy()->end();

        $user = User::factory()->create([
            'email' => 'pm_'.$id.'@acme.test',
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
            'tenantId' => $tenant->id,
            'branchId' => (int) $branchId,
            'user' => $user,
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
