<?php

namespace App\Http\Middleware;

use App\Services\BillingService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimit
{
    public function handle(Request $request, Closure $next, string $resource): Response
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403);
        if (app(BillingService::class)->limitReached($tenant, $resource)) {
            return redirect()->route('billing.index')->with('billing_limit', ucfirst($resource).' limit reached for your plan.');
        }
        return $next($request);
    }
}
