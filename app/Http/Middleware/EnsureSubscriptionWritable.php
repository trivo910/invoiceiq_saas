<?php

namespace App\Http\Middleware;

use App\Services\BillingService;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionWritable
{
    public function __construct(private BillingService $billing, private TenantContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->context->current();
        abort_unless($tenant, 403);
        if (! $this->billing->canWrite($tenant)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'This workspace is read-only. Review billing to restore changes.'], 403);
            }
            return redirect()->route('billing.index')->with('billing_read_only', 'This workspace is read-only. Review billing to restore changes.');
        }
        return $next($request);
    }
}
