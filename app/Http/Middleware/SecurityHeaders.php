<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        return $this->apply($request, $next($request));
    }

    public function apply(Request $request, \Symfony\Component\HttpFoundation\Response $response): \Symfony\Component\HttpFoundation\Response
    {
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        if (! $response->headers->has('Referrer-Policy')) $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'none'");
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->user() || auth('superadmin')->check()) $response->headers->set('Cache-Control', 'private, no-store');
        if (app()->environment('production') && $request->isSecure()) $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        return $response;
    }
}
