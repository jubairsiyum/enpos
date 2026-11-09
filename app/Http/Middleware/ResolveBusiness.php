<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Support\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBusiness
{
    public function __construct(private CurrentBusiness $currentBusiness) {}

    public function handle(Request $request, Closure $next): Response
    {
        $business = $this->resolve($request);

        if (! $business) {
            return response()->json(['message' => 'Shop not found.'], 404);
        }

        if ($business->status === 'suspended') {
            return response()->json(['message' => 'This shop has been suspended.'], 403);
        }

        if (! $business->activeSubscription) {
            return response()->json(['message' => 'No active subscription.'], 402);
        }

        $this->currentBusiness->set($business);
        if (function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId($business->id);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?Business
    {
        // 1. Explicit header (for API clients / mobile)
        if ($header = $request->header('X-Business')) {
            return Business::withoutGlobalScope('business')
                ->where('slug', $header)
                ->orWhere('id', $header)
                ->with('activeSubscription')
                ->first();
        }

        // 2. Subdomain: slug.yourapp.test
        $host = $request->getHost();
        $baseDomain = config('app.base_domain');

        if ($baseDomain && str_ends_with($host, '.'.$baseDomain)) {
            $slug = substr($host, 0, -strlen('.'.$baseDomain));

            return Business::withoutGlobalScope('business')
                ->where('slug', $slug)
                ->with('activeSubscription')
                ->first();
        }

        // 3. Custom domain
        if ($business = Business::withoutGlobalScope('business')
            ->where('custom_domain', $host)
            ->with('activeSubscription')
            ->first()) {
            return $business;
        }

        // 4. Development / single-domain fallback: the user's own business
        if (app()->environment('local') && ($user = $request->user()) && $user->business_id) {
            return Business::withoutGlobalScope('business')
                ->whereKey($user->business_id)
                ->with('activeSubscription')
                ->first();
        }

        // 5. SuperAdmin access fallback: allows SuperAdmin to access the POS shop directly
        if (($user = $request->user()) && ($user->isSuperAdmin() || $user->isPlatformAdmin())) {
            return Business::withoutGlobalScope('business')
                ->with('activeSubscription')
                ->first();
        }

        return null;
    }
}
