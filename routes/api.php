<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// -----------------------------------------------------------------------
// Public
// -----------------------------------------------------------------------
Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login',   [AuthController::class, 'login']);
        Route::post('logout',  [AuthController::class, 'logout'])->middleware('auth:sanctum');
        Route::get('me',       [AuthController::class, 'me'])->middleware('auth:sanctum');
    });

    // -----------------------------------------------------------------------
    // Platform routes (super_admin / admin only)
    // -----------------------------------------------------------------------
    Route::prefix('platform')
        ->middleware(['auth:sanctum', 'platform:super_admin,admin'])
        ->group(function () {
            // Businesses
            Route::apiResource('businesses', \App\Http\Controllers\Platform\BusinessController::class);
            Route::post('businesses/{business}/suspend',   [\App\Http\Controllers\Platform\BusinessController::class, 'suspend']);
            Route::post('businesses/{business}/activate',  [\App\Http\Controllers\Platform\BusinessController::class, 'activate']);
            Route::post('businesses/{business}/impersonate', [\App\Http\Controllers\Platform\BusinessController::class, 'impersonate']);

            // Plans
            Route::apiResource('plans', \App\Http\Controllers\Platform\PlanController::class);

            // Subscriptions
            Route::apiResource('subscriptions', \App\Http\Controllers\Platform\SubscriptionController::class)->only(['index', 'show', 'update']);
        });

    // -----------------------------------------------------------------------
    // Shop routes (resolved by business middleware)
    // -----------------------------------------------------------------------
    Route::prefix('shop')
        ->middleware(['auth:sanctum', 'business'])
        ->group(function () {

            // Settings
            Route::prefix('settings')->group(function () {
                Route::get('/',    [\App\Http\Controllers\Shop\SettingController::class, 'index']);
                Route::post('/',   [\App\Http\Controllers\Shop\SettingController::class, 'update']);
                Route::get('theme', [\App\Http\Controllers\Shop\SettingController::class, 'theme']);
                Route::post('theme', [\App\Http\Controllers\Shop\SettingController::class, 'updateTheme']);
            });

            // Locations
            Route::apiResource('locations', \App\Http\Controllers\Shop\LocationController::class);

            // Users & Roles (User Management)
            Route::apiResource('users', \App\Http\Controllers\Shop\UserController::class);
            Route::get('roles/permissions', [\App\Http\Controllers\Shop\RoleController::class, 'permissions']);
            Route::apiResource('roles', \App\Http\Controllers\Shop\RoleController::class);
            Route::apiResource('sales-commission-agents', \App\Http\Controllers\Shop\SalesCommissionAgentController::class);

            // Contacts
            Route::apiResource('contacts', \App\Http\Controllers\Shop\ContactController::class);

            // Products
            Route::apiResource('products', \App\Http\Controllers\Shop\ProductController::class);

            // Purchases
            Route::apiResource('purchases', \App\Http\Controllers\Shop\PurchaseController::class);

            // Sell
            Route::apiResource('sells', \App\Http\Controllers\Shop\SellController::class);

            // POS
            Route::prefix('pos')->middleware('module:pos')->group(function () {
                Route::get('session',  [\App\Http\Controllers\Shop\PosController::class, 'session']);
                Route::post('session', [\App\Http\Controllers\Shop\PosController::class, 'openSession']);
                Route::patch('session/{register}/close', [\App\Http\Controllers\Shop\PosController::class, 'closeSession']);
                Route::post('sell',    [\App\Http\Controllers\Shop\PosController::class, 'sell']);
            });

            // Stock
            Route::apiResource('stock-transfers',   \App\Http\Controllers\Shop\StockTransferController::class);
            Route::apiResource('stock-adjustments', \App\Http\Controllers\Shop\StockAdjustmentController::class);

            // Expenses
            Route::apiResource('expenses',           \App\Http\Controllers\Shop\ExpenseController::class);
            Route::apiResource('expense-categories', \App\Http\Controllers\Shop\ExpenseCategoryController::class);

            // Finance
            Route::apiResource('accounts', \App\Http\Controllers\Shop\AccountController::class);

            // Reports
            Route::prefix('reports')->middleware('module:reports')->group(function () {
                Route::get('profit-loss',  [\App\Http\Controllers\Shop\ReportController::class, 'profitLoss']);
                Route::get('stock',        [\App\Http\Controllers\Shop\ReportController::class, 'stock']);
                Route::get('tax',          [\App\Http\Controllers\Shop\ReportController::class, 'tax']);
                Route::get('register',     [\App\Http\Controllers\Shop\ReportController::class, 'register']);
            });

            // HRM (optional module)
            Route::prefix('hrm')->middleware('module:hrm')->group(function () {
                Route::apiResource('departments',  \App\Http\Controllers\Shop\Hrm\DepartmentController::class);
                Route::apiResource('designations', \App\Http\Controllers\Shop\Hrm\DesignationController::class);
                Route::apiResource('employees',    \App\Http\Controllers\Shop\Hrm\EmployeeController::class);
                Route::apiResource('attendances',  \App\Http\Controllers\Shop\Hrm\AttendanceController::class);
                Route::apiResource('leaves',       \App\Http\Controllers\Shop\Hrm\LeaveController::class);
                Route::apiResource('payrolls',     \App\Http\Controllers\Shop\Hrm\PayrollController::class);
            });

            // Essentials (optional module)
            Route::prefix('essentials')->middleware('module:essentials')->group(function () {
                Route::apiResource('todos',     \App\Http\Controllers\Shop\Essentials\TodoController::class);
                Route::apiResource('documents', \App\Http\Controllers\Shop\Essentials\DocumentController::class);
                Route::apiResource('reminders', \App\Http\Controllers\Shop\Essentials\ReminderController::class);
            });

            // Cheque (optional module)
            Route::prefix('cheques')->middleware('module:cheque')->group(function () {
                Route::apiResource('/', \App\Http\Controllers\Shop\ChequeController::class)->parameters(['' => 'cheque']);
            });

            // Woocommerce (optional module)
            Route::prefix('woocommerce')->middleware('module:woocommerce')->group(function () {
                Route::get('settings',    [\App\Http\Controllers\Shop\WoocommerceController::class, 'settings']);
                Route::post('settings',   [\App\Http\Controllers\Shop\WoocommerceController::class, 'updateSettings']);
                Route::post('sync/products', [\App\Http\Controllers\Shop\WoocommerceController::class, 'syncProducts']);
                Route::post('sync/orders',   [\App\Http\Controllers\Shop\WoocommerceController::class, 'syncOrders']);
            });
        });
});

