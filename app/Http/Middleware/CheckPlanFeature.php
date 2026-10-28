<?php

namespace App\Http\Middleware;

use App\Services\BillingService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $tenant = app(TenantContext::class)->current();
        abort_unless($tenant, 403);
        $plan = app(BillingService::class)->subscription($tenant)?->plan;
        abort_unless($plan, 403);

        // Null means this entitlement has not been decided/configured yet; false is an explicit denial.
        if ($plan->getAttribute($feature) === false) {
            return \App\Support\PlanRestrictionResponse::make($request, 'billing_feature', 'This feature is not included in your configured plan.');
        }
        return $next($request);
    }
}
