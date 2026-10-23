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
        $tenant = $request->user()?->tenants()->orderBy('tenants.id')->first();
        abort_unless($tenant, 403, 'Your account is not associated with a workspace.');

        $this->context->set($tenant);
        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
