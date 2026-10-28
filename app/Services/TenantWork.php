<?php
namespace App\Services;

use App\Models\Tenant;
use App\Support\TenantContext;

class TenantWork
{
    public function run(int $tenantId, callable $callback): mixed
    {
        $context = app(TenantContext::class);
        $previous = $context->current();
        $tenant = Tenant::findOrFail($tenantId);
        abort_unless($tenant->status === 'active', 403, 'Workspace is suspended.');
        $context->set($tenant);
        try { return $callback($tenant); }
        finally { $previous ? $context->set($previous) : $context->clear(); }
    }
}
