<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\TenantLogoStorage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tenants:migrate-legacy-logos', function (TenantLogoStorage $storage) {
    $count = $storage->migrateAllLegacyFiles();
    $this->info("Moved legacy workspace logo references to private storage: {$count}.");
})->purpose('Move legacy public workspace logos into tenant-private storage');
