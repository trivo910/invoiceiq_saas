<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PlanRestrictionResponse
{
    public static function make(Request $request, string $key, string $message): Response
    {
        $owner = $request->user()?->hasRole('owner') ?? false;
        $message .= $owner ? ' Review billing to restore access.' : ' Contact your workspace owner to restore access.';

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return $owner
            ? redirect()->route('billing.index')->with($key, $message)
            : redirect()->route('dashboard')->with('warning', $message);
    }
}
