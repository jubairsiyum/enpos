<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = [
            'total_businesses'    => Business::withoutGlobalScope('business')->count(),
            'active_businesses'   => Business::withoutGlobalScope('business')->active()->count(),
            'suspended_businesses'=> Business::withoutGlobalScope('business')->suspended()->count(),
            'active_subscriptions'=> Subscription::active()->count(),
        ];

        $recentBusinesses = Business::withoutGlobalScope('business')
            ->with('activeSubscription.plan')
            ->latest()
            ->take(8)
            ->get();

        return Inertia::render('Platform/Dashboard', [
            'stats'              => $stats,
            'recentBusinesses'   => $recentBusinesses,
        ]);
    }
}

