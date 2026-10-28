<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\TenantLogoStorage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('superadmin:create', function () {
    $name = $this->ask('Name');
    $email = $this->ask('Email');
    $password = $this->secret('Password (at least 12 characters)');
    $data = validator(compact('name', 'email', 'password'), [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', 'unique:super_admins,email'],
        'password' => ['required', 'string', 'min:12'],
    ])->validate();
    \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
        $admin = \App\Models\SuperAdmin::create($data);
        \App\Models\AdminAuditLog::create(['super_admin_id' => $admin->id, 'action' => 'admin.created', 'details' => ['source' => 'console']]);
    });
    $this->info('Super-admin created. Sign in at /superadmin/login.');
})->purpose('Create a separate platform administrator using a hidden password prompt');

Artisan::command('tenants:migrate-legacy-logos', function (TenantLogoStorage $storage) {
    $count = $storage->migrateAllLegacyFiles();
    $this->info("Moved legacy workspace logo references to private storage: {$count}.");
})->purpose('Move legacy public workspace logos into tenant-private storage');

Artisan::command('workflows:prune', function () {
    $count = 0;
    foreach (\App\Models\Tenant::orderBy('id')->cursor() as $tenant) {
        $rows = \Illuminate\Support\Facades\DB::table('account_exports')->where('tenant_id', $tenant->id)
            ->where('expires_at', '<=', now())->whereNotNull('path')->get();
        foreach ($rows as $row) {
            if (preg_match('~^tenants/'.preg_quote((string) $tenant->id, '~').'/exports/[a-f0-9-]+\\.zip$~D', $row->path)) {
                \Illuminate\Support\Facades\Storage::disk('tenant_private')->delete($row->path);
                \Illuminate\Support\Facades\DB::table('account_exports')->where('tenant_id', $tenant->id)->where('id', $row->id)->update(['path' => null, 'status' => 'expired', 'updated_at' => now()]);
                $count++;
            }
        }
    }
    $this->info("Expired workspace exports removed: {$count}");
})->purpose('Remove expired private workspace export files using tenant-validated paths');

Schedule::command('queue:work database --stop-when-empty --max-time=50 --tries=3 --timeout=60')->everyMinute()->withoutOverlapping(2);
Schedule::command('workflows:prune')->daily()->withoutOverlapping();
