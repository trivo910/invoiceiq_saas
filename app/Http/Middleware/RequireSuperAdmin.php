<?php

namespace App\Http\Middleware;

use App\Models\AdminAuditLog;
use App\Services\SuperAdminService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next)
    {
        if (! Auth::guard('superadmin')->check()) {
            if (Auth::guard('web')->check()) {
                AdminAuditLog::create(['action' => 'access.denied', 'details' => ['path' => $request->path(), 'tenant_user_id' => Auth::guard('web')->id()], 'ip_address' => $request->ip()]);
                abort(403);
            }
            return $request->expectsJson() ? response()->json(['message' => 'Super-admin authentication required.'], 401) : redirect()->route('superadmin.login');
        }
        $service = app(SuperAdminService::class);
        $service->authorize();
        $service->audit('access', details: ['method' => $request->method(), 'path' => $request->path()]);
        return $next($request);
    }
}
