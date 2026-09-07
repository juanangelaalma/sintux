<?php

namespace Modules\Company\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Warehouse\Database\Seeders\WarehouseSystemSeeder;

class CompanyDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);
        $this->call(WarehouseSystemSeeder::class);
    }
}
