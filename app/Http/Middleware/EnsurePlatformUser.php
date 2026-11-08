<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformUser
{
    /**
     * Only allow super_admin or admin (platform-level users).
     * Pass a comma-separated list of allowed types as a middleware parameter.
     * Example: middleware('platform:super_admin') or middleware('platform:super_admin,admin')
     */
    public function handle(Request $request, Closure $next, string $types = 'super_admin,admin'): Response
    {
        $user          = $request->user();
        $allowedTypes  = explode(',', $types);

        if (! $user || ! in_array($user->type, $allowedTypes)) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        return $next($request);
    }
}

