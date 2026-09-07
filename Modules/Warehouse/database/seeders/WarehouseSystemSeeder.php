<?php

namespace Modules\Warehouse\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Warehouse\Application\Warehouse\CreateWarehousesForBranch;

class WarehouseSystemSeeder extends Seeder
{
    public function run(): void
    {
        app(CreateWarehousesForBranch::class)->ensureForAllBranches();
    }
}
