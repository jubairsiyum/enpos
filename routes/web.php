<?php

use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Platform\BusinessController;
use App\Http\Controllers\Platform\DashboardController;
use App\Http\Controllers\Platform\PlanController;
use App\Http\Controllers\Platform\SubscriptionController;
use App\Http\Controllers\Platform\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes — Inertia SPA
|--------------------------------------------------------------------------
*/

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login', fn () => Inertia::render('Auth/Login'))->name('login');
    Route::post('/login', [SessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});

// Platform admin
Route::prefix('platform')
    ->middleware(['auth', 'platform:super_admin,admin'])
    ->name('platform.')
    ->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('dashboard');

        Route::resource('businesses', BusinessController::class)
            ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);

        Route::resource('plans', PlanController::class);
        Route::resource('subscriptions', SubscriptionController::class)
            ->only(['index', 'show', 'edit', 'update']);
        Route::resource('users', UserController::class);
    });

// Shop (tenant)
Route::prefix('app')
    ->middleware(['auth', 'business'])
    ->name('shop.')
    ->group(function () {
        Route::get('/dashboard', fn () => Inertia::render('Shop/Dashboard'))->name('dashboard');

        // User Management
        Route::resource('users', \App\Http\Controllers\Shop\UserController::class);
        Route::resource('roles', \App\Http\Controllers\Shop\RoleController::class);
        Route::resource('sales-commission-agents', \App\Http\Controllers\Shop\SalesCommissionAgentController::class);

        // Remaining routes will be added per module
        Route::get('/{any}', fn () => Inertia::render('Shop/Dashboard'))
            ->where('any', '.*')
            ->name('catch-all');
    });

// Root redirect
Route::get('/', function () {
    if (auth()->check()) {
        $user = auth()->user();

        return redirect()->route($user->isPlatformUser() ? 'platform.dashboard' : 'shop.dashboard');
    }

    return redirect()->route('login');
});
