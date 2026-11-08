<?php

namespace App\Http\Middleware;

use App\Support\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(private CurrentBusiness $currentBusiness) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $business = $this->currentBusiness->get();

        if (! $business) {
            return response()->json(['message' => 'No business context.'], 400);
        }

        $coreModules     = config('modules.core', []);
        $optionalModules = config('modules.optional', []);

        // Core modules are always available
        if (in_array($module, $coreModules)) {
            return $next($request);
        }

        // Optional modules: check plan allows it, then check shop toggle
        if (in_array($module, $optionalModules)) {
            $planModules = $business->activeSubscription?->plan?->modules ?? [];

            if (! in_array($module, $planModules)) {
                return response()->json([
                    'message' => "Module [{$module}] is not included in your plan.",
                ], 403);
            }

            $shopModules = $business->enabled_modules ?? [];

            if (! in_array($module, $shopModules)) {
                return response()->json([
                    'message' => "Module [{$module}] is not enabled for this shop.",
                ], 403);
            }
        }

        return $next($request);
    }
}

