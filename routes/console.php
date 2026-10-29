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

Artisan::command('settings:encrypt-smtp-passwords', function () {
    if (! config('app.key')) { $this->error('APP_KEY is required to encrypt stored SMTP credentials.'); return 1; }
    $updated = 0;
    foreach (\App\Models\Tenant::withTrashed()->orderBy('id')->cursor() as $tenant) {
        $context = app(\App\Support\TenantContext::class);
        $context->set($tenant);
        $row = \App\Models\Setting::where('key', 'smtp_password')->first();
        if ($row && ! str_starts_with((string) $row->getRawOriginal('value'), 'enc:v1:')) {
            $plaintext = $row->getRawOriginal('value');
            $row->value = $plaintext;
            $row->save();
            $updated++;
        }
        \Illuminate\Support\Facades\Cache::forget('setting_'.$tenant->id.'_smtp_password');
        $context->clear();
    }
    $this->info("Encrypted legacy SMTP password rows: {$updated}. No credential values were printed.");
})->purpose('Encrypt legacy plaintext workspace SMTP passwords without printing them');

Artisan::command('release:check {--production}', function () {
    $production = (bool) $this->option('production');
    $billing = config('billing');
    $paidBillingReady = filled(data_get($billing, 'razorpay.key')) && filled(data_get($billing, 'razorpay.secret'))
        && filled(data_get($billing, 'razorpay.webhook_secret'))
        && (int) data_get($billing, 'subscription_cycle_count.monthly') > 0
        && (int) data_get($billing, 'subscription_cycle_count.yearly') > 0
        && is_numeric(data_get($billing, 'grace_days'))
        && filled(data_get($billing, 'seller.name')) && filled(data_get($billing, 'seller.gstin'))
        && filled(data_get($billing, 'seller.pan')) && filled(data_get($billing, 'seller.address'))
        && filled(data_get($billing, 'seller.state_code')) && is_numeric(data_get($billing, 'seller.gst_rate'))
        && filled(data_get($billing, 'seller.sac_code'))
        && in_array(data_get($billing, 'seller.price_tax_mode'), ['inclusive', 'exclusive'], true);
    $privateRoot = config('filesystems.disks.tenant_private.root');
    $checks = [
        'APP_KEY configured' => filled(config('app.key')),
        'APP_DEBUG disabled' => ! config('app.debug'),
        'MySQL driver selected' => config('database.default') === 'mysql',
        'Database reachable' => false,
        'No pending migrations' => false,
        'Production billing demo disabled' => $production ? ! config('billing.demo_mode') : null,
        'Paid billing environment configured' => $production ? $paidBillingReady : null,
        'Legal pages approved and identified' => $production ? (bool) (config('launch.legal_approved') && filled(config('launch.legal_entity')) && filter_var(config('launch.contact_email'), FILTER_VALIDATE_EMAIL)) : null,
        'Pricing approved' => $production ? (bool) config('launch.pricing_approved') : null,
        'Super-admin provisioned' => false,
        'HTTPS APP_URL in production' => $production ? str_starts_with((string) config('app.url'), 'https://') : null,
        'Secure session cookie in production' => $production ? (bool) config('session.secure') : null,
        'Database queue configured' => $production ? config('queue.default') === 'database' : null,
        'SMTP mailer configured' => $production ? config('mail.default') === 'smtp' : null,
        'Stored SMTP passwords encrypted with current APP_KEY' => $production ? false : null,
        'PHP 8.2 or newer' => PHP_VERSION_ID >= 80200,
        'Production PHP extensions available' => $production ? (extension_loaded('pdo_mysql') && extension_loaded('openssl') && extension_loaded('zip')) : null,
        'Private tenant storage writable' => $production ? (is_string($privateRoot) && is_dir($privateRoot) && is_writable($privateRoot)) : null,
    ];
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $checks['Database reachable'] = true;
        $checks['No pending migrations'] = \Illuminate\Support\Facades\Artisan::call('migrate:status') === 0
            && ! str_contains(\Illuminate\Support\Facades\Artisan::output(), 'Pending');
        $checks['Super-admin provisioned'] = \App\Models\SuperAdmin::where('is_active', true)->exists();
        if ($production) {
            $smtpPasswords = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'smtp_password')
                ->whereNotNull('value')->where('value', '<>', '')->pluck('value');
            $checks['Stored SMTP passwords encrypted with current APP_KEY'] = $smtpPasswords->every(function ($value) {
                if (! is_string($value) || ! str_starts_with($value, 'enc:v1:')) return false;
                try {
                    \Illuminate\Support\Facades\Crypt::decryptString(substr($value, 7));
                    return true;
                } catch (\Throwable) {
                    return false;
                }
            });
        }
    } catch (\Throwable) { }
    foreach ($checks as $label => $ok) {
        $status = $ok === null ? '<fg=yellow>SKIP</>' : ($ok ? '<fg=green>PASS</>' : '<fg=red>CHECK</>');
        $this->line($status.' '.$label);
    }
    $this->warn('This configuration check does not verify provider QA, SMTP delivery, backups/restores, DNS/TLS, browser behavior or server deployment.');
    $failed = in_array(false, $checks, true);
    return $this->option('production') && $failed ? 1 : 0;
})->purpose('Report local/production release prerequisites without displaying secrets');
