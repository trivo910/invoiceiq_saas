<?php

namespace App\Http\Middleware;

use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(TenantContext::class)->tenant()?->onboarding_completed_at) {
            return redirect()->route('onboarding.show');
        }
        return $next($request);
    }
}
