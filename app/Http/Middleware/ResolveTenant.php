<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $memberships = $request->user()?->tenants();
        $selectedId = $request->session()->get('current_tenant_id');
        $tenant = $selectedId ? $memberships?->where('tenants.id', $selectedId)->first() : null;
        $tenant ??= $memberships?->orderBy('tenants.id')->first();
        abort_unless($tenant, 403, 'Your account is not associated with a workspace.');
        abort_unless($tenant->status === 'active', 403, 'This workspace is suspended. Contact support.');

        $this->context->set($tenant);
        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
