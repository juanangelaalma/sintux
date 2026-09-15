<?php

namespace Modules\Company\Tests\Feature;

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TenantSeededOnCreationTest extends TestCase
{
    private ?string $activeSchemaName = null;

    protected function tearDown(): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        if ($this->activeSchemaName) {
            try {
                DB::statement('DROP SCHEMA IF EXISTS "'.$this->activeSchemaName.'" CASCADE');
            } catch (\Throwable $e) {
            }

            $this->activeSchemaName = null;
        }

        parent::tearDown();
    }

    public function test_tenant_creation_seeds_chart_of_accounts_and_default_tax(): void
    {
        $id = uniqid('seed_');
        $this->activeSchemaName = 'sch_'.$id;

        $tenant = Tenant::create([
            'id' => $id,
            'name' => 'Seeded Company-'.$id,
            'schema_name' => $this->activeSchemaName,
            'is_active' => true,
        ]);

        tenancy()->initialize($tenant);

        $this->assertGreaterThan(
            0,
            DB::table('chart_of_accounts')->count(),
            'CoA harus ter-seed otomatis saat tenant dibuat (pipeline SeedDatabase).'
        );

        $ppn = DB::table('taxes')->where('code', 'PPN')->first();

        $this->assertNotNull($ppn, 'Pajak default PPN harus ada setelah tenant dibuat.');
        $this->assertNotNull($ppn->input_account_id, 'PPN default harus terpetakan ke akun PPN Masukan.');
        $this->assertNotNull($ppn->output_account_id, 'PPN default harus terpetakan ke akun PPN Keluaran.');
        $this->assertEquals(12, (float) $ppn->rate, 'PPN default tenant baru = 12%.');
        $this->assertTrue((bool) $ppn->dpp_multiplier, 'PPN default memakai pengali 11/12 (DPP Nilai Lain).');
    }
}
