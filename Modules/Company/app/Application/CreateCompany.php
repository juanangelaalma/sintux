<?php

namespace Modules\Company\Application;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Company\Application\Exceptions\CompanyCreationFailed;
use Modules\Company\Models\CompanyUser;

class CreateCompany
{
    /**
     * Create a company (tenant) together with its managing admin user.
     *
     * @param  array{id: string, name: string, schema_name: string, is_active?: bool, admin_name: string, admin_email: string, admin_password: string}  $data
     */
    public function execute(array $data): Tenant
    {
        $tenant = null;
        $admin = null;

        try {
            // Creating the tenant also creates and migrates its schema via TenantCreated event.
            $tenant = Tenant::create([
                'id' => $data['id'],
                'name' => $data['name'],
                'schema_name' => $data['schema_name'],
                'is_active' => $data['is_active'] ?? true,
            ]);

            $admin = User::create([
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'role' => 'admin',
                'password' => bcrypt($data['admin_password']),
            ]);

            CompanyUser::create([
                'user_id' => $admin->id,
                'tenant_id' => $tenant->id,
                'role' => 'owner',
                'is_default' => true,
            ]);

            $this->createHeadquartersBranch($tenant);

            return $tenant;
        } catch (\Exception $e) {
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            if ($admin) {
                $admin->delete();
            }

            // Tenant::create may fail after the row was already persisted (e.g. schema
            // creation failed), so re-fetch it to ensure nothing is left behind.
            $tenant = $tenant ?? Tenant::find($data['id']);
            if ($tenant) {
                $tenant->delete();
            }

            throw new CompanyCreationFailed('Failed to create database schema or user: '.$e->getMessage(), 0, $e);
        }
    }

    private function createHeadquartersBranch(Tenant $tenant): void
    {
        // Ensure tenant migrations have executed synchronously before querying tenant tables
        Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        tenancy()->initialize($tenant);

        try {
            if (Schema::hasTable('branches')) {
                $existing = DB::table('branches')->where('code', 'HO')->first();

                if (! $existing) {
                    DB::table('branches')->insert([
                        'name' => 'Head Office',
                        'code' => 'HO',
                        'is_headquarters' => true,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        } finally {
            tenancy()->end();
        }
    }
}
