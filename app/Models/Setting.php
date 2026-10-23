<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use App\Models\Concerns\BelongsToTenant;
use App\Services\InvoiceNumberingService;
use App\Support\TenantContext;

class Setting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        if ($key === 'invoice_seq' && app(TenantContext::class)->id()) {
            return app(InvoiceNumberingService::class)->nextNumberForCurrentYear();
        }

        $tenantId = app(TenantContext::class)->id();
        return Cache::rememberForever("setting_{$tenantId}_{$key}", function () use ($key, $default) {
            $row = static::where('key', $key)->first();
            return $row ? $row->value : $default;
        });
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        $tenantId = app(TenantContext::class)->id();
        Cache::forget("setting_{$tenantId}_{$key}");

        if ($key === 'invoice_prefix' && $tenantId) {
            \App\Models\Tenant::whereKey($tenantId)->update(['invoice_prefix' => $value]);
        }
        if ($key === 'invoice_seq' && $tenantId) {
            app(InvoiceNumberingService::class)->setNextNumberForCurrentYear((int) $value);
        }
    }

    public static function all_settings(): array
    {
        return static::all()->pluck('value', 'key')->toArray();
    }
}
