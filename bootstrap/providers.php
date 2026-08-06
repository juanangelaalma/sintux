<?php

use App\Providers\AccessServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\TenancyServiceProvider;

return [
    AppServiceProvider::class,
    AccessServiceProvider::class,
    FortifyServiceProvider::class,
    TenancyServiceProvider::class,
];
