<?php

namespace App\Http\Middleware;

use App\Support\CurrentBusiness;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Share auth user, business context, and flash messages with every page.
     */
    public function share(Request $request): array
    {
        $user     = $request->user();
        $business = app(CurrentBusiness::class)->get();

        return [
            ...parent::share($request),

            'auth' => [
                'user'     => $user ? [
                    'id'          => $user->id,
                    'name'        => $user->name,
                    'email'       => $user->email,
                    'type'        => $user->type,
                    'business_id' => $user->business_id,
                    'avatar'      => $user->avatar,
                ] : null,
                'business' => $business ? [
                    'id'              => $business->id,
                    'name'            => $business->name,
                    'slug'            => $business->slug,
                    'logo'            => $business->logo,
                    'currency_symbol' => $business->currency_symbol,
                ] : null,
            ],

            'enabledModules' => $business?->enabled_modules ?? [],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
