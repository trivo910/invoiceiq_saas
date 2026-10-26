<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use LogicException;

class DashboardCache
{
    public static function key(): string
    {
        $tenantId = app(TenantContext::class)->id();
        if (! $tenantId) {
            throw new LogicException('A tenant context is required for dashboard caching.');
        }

        return "dashboard.stats.tenant.{$tenantId}";
    }

    public static function forget(): void
    {
        Cache::forget(self::key());
    }
}
