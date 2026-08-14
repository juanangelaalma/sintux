<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$migs = DB::select("SELECT migration FROM company_aii.migrations ORDER BY id");
foreach ($migs as $m) echo $m->migration . PHP_EOL;