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
        Route::get('/dashboard', fn () => redirect()->route('shop.users.index'))
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
        Route::get('/dashboard', fn () => redirect()->route('shop.users.index'))->name('dashboard');

        // User Management
        Route::resource('users', \App\Http\Controllers\Shop\UserController::class);
        Route::resource('roles', \App\Http\Controllers\Shop\RoleController::class);
        Route::resource('sales-commission-agents', \App\Http\Controllers\Shop\SalesCommissionAgentController::class);

        // Contacts (Suppliers / Customers / Customer Groups)
        Route::post('contacts/customers/{id}/restore', [\App\Http\Controllers\Shop\CustomerController::class, 'restore'])->name('customers.restore');
        Route::delete('contacts/customers/{id}/force-delete', [\App\Http\Controllers\Shop\CustomerController::class, 'forceDelete'])->name('customers.force-delete');
        Route::resource('contacts/customers', \App\Http\Controllers\Shop\CustomerController::class)
            ->parameters(['customers' => 'customer'])
            ->names('customers')
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::resource('contacts/customer-groups', \App\Http\Controllers\Shop\CustomerGroupController::class)
            ->parameters(['customer-groups' => 'customerGroup'])
            ->names('customer-groups')
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::get('contacts/import', [\App\Http\Controllers\Shop\ImportContactController::class, 'index'])->name('import-contacts.index');
        Route::post('contacts/import', [\App\Http\Controllers\Shop\ImportContactController::class, 'store'])->name('import-contacts.store');
        Route::get('contacts/import/template', [\App\Http\Controllers\Shop\ImportContactController::class, 'downloadTemplate'])->name('import-contacts.template');

        Route::post('contacts/{contact}/pay', [\App\Http\Controllers\Shop\ContactActionController::class, 'pay'])->name('contacts.pay');
        Route::post('contacts/{contact}/toggle-status', [\App\Http\Controllers\Shop\ContactActionController::class, 'toggleStatus'])->name('contacts.toggle-status');
        Route::get('contacts/{contact}/ledger', [\App\Http\Controllers\Shop\ContactActionController::class, 'ledger'])->name('contacts.ledger');
        Route::get('contacts/{contact}/sales', [\App\Http\Controllers\Shop\ContactActionController::class, 'sales'])->name('contacts.sales');
        Route::get('contacts/{contact}/documents', [\App\Http\Controllers\Shop\ContactActionController::class, 'documents'])->name('contacts.documents.index');
        Route::post('contacts/{contact}/documents', [\App\Http\Controllers\Shop\ContactActionController::class, 'storeDocument'])->name('contacts.documents.store');
        Route::get('contacts/{contact}/documents/{document}/download', [\App\Http\Controllers\Shop\ContactActionController::class, 'downloadDocument'])->name('contacts.documents.download');
        Route::delete('contacts/{contact}/documents/{document}', [\App\Http\Controllers\Shop\ContactActionController::class, 'destroyDocument'])->name('contacts.documents.destroy');

        Route::post('contacts/{id}/restore', [\App\Http\Controllers\Shop\ContactController::class, 'restore'])->name('contacts.restore');
        Route::delete('contacts/{id}/force-delete', [\App\Http\Controllers\Shop\ContactController::class, 'forceDelete'])->name('contacts.force-delete');
        Route::resource('contacts', \App\Http\Controllers\Shop\ContactController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        // Products (List Products, Add Product, Update Price, Print Labels)
        Route::get('products/create', [\App\Http\Controllers\Shop\AddProductController::class, 'create'])->name('products.create');
        Route::post('products/quick-units', [\App\Http\Controllers\Shop\AddProductController::class, 'quickStoreUnit'])->name('products.quick-units');
        Route::post('products/quick-brands', [\App\Http\Controllers\Shop\AddProductController::class, 'quickStoreBrand'])->name('products.quick-brands');
        Route::get('products/update-price', [\App\Http\Controllers\Shop\UpdatePriceController::class, 'index'])->name('products.update-price.index');
        Route::get('products/update-price/export', [\App\Http\Controllers\Shop\UpdatePriceController::class, 'export'])->name('products.update-price.export');
        Route::post('products/update-price/import', [\App\Http\Controllers\Shop\UpdatePriceController::class, 'import'])->name('products.update-price.import');
        Route::get('products/print-labels', [\App\Http\Controllers\Shop\PrintLabelController::class, 'index'])->name('products.print-labels.index');
        Route::get('products/print-labels/search', [\App\Http\Controllers\Shop\PrintLabelController::class, 'search'])->name('products.print-labels.search');
        Route::post('products/print-labels/preview', [\App\Http\Controllers\Shop\PrintLabelController::class, 'preview'])->name('products.print-labels.preview');
        Route::post('products/print-labels/preview-pdf', [\App\Http\Controllers\Shop\PrintLabelController::class, 'previewPdf'])->name('products.print-labels.preview-pdf');
        Route::post('products/print-labels/barcode-settings', [\App\Http\Controllers\Shop\PrintLabelController::class, 'storeBarcodeSetting'])->name('products.print-labels.barcode-settings.store');
        Route::match(['put', 'patch'], 'products/print-labels/barcode-settings/{barcodeSetting}', [\App\Http\Controllers\Shop\PrintLabelController::class, 'updateBarcodeSetting'])->name('products.print-labels.barcode-settings.update');
        Route::delete('products/print-labels/barcode-settings/{barcodeSetting}', [\App\Http\Controllers\Shop\PrintLabelController::class, 'destroyBarcodeSetting'])->name('products.print-labels.barcode-settings.destroy');
        Route::post('products/bulk-delete', [\App\Http\Controllers\Shop\ProductController::class, 'bulkDestroy'])->name('products.bulk-delete');
        Route::post('products/bulk-location', [\App\Http\Controllers\Shop\ProductController::class, 'bulkLocation'])->name('products.bulk-location');
        Route::post('products/bulk-status', [\App\Http\Controllers\Shop\ProductController::class, 'bulkToggleStatus'])->name('products.bulk-status');
        Route::post('products/bulk-woocommerce-sync', [\App\Http\Controllers\Shop\ProductController::class, 'bulkWooCommerceSync'])->name('products.bulk-woocommerce-sync');
        Route::post('products/{id}/restore', [\App\Http\Controllers\Shop\ProductController::class, 'restore'])->name('products.restore');
        Route::delete('products/{id}/force-delete', [\App\Http\Controllers\Shop\ProductController::class, 'forceDelete'])->name('products.force-delete');
        Route::post('products/{product}/duplicate', [\App\Http\Controllers\Shop\ProductController::class, 'duplicate'])->name('products.duplicate');
        Route::get('products/{product}/opening-stock', [\App\Http\Controllers\Shop\ProductController::class, 'openingStock'])->name('products.opening-stock');
        Route::post('products/{product}/opening-stock', [\App\Http\Controllers\Shop\ProductController::class, 'saveOpeningStock'])->name('products.save-opening-stock');
        Route::get('products/{product}/stock-history', [\App\Http\Controllers\Shop\ProductController::class, 'stockHistory'])->name('products.stock-history');
        Route::get('products/{product}/labels', [\App\Http\Controllers\Shop\ProductController::class, 'labels'])->name('products.labels');
        Route::get('products', [\App\Http\Controllers\Shop\ProductController::class, 'index'])->name('products.index');
        Route::post('products', [\App\Http\Controllers\Shop\AddProductController::class, 'store'])->name('products.store');
        Route::get('products/{product}', [\App\Http\Controllers\Shop\ProductController::class, 'show'])->name('products.show');
        Route::get('products/{product}/edit', [\App\Http\Controllers\Shop\ProductController::class, 'edit'])->name('products.edit');
        Route::match(['put', 'patch'], 'products/{product}', [\App\Http\Controllers\Shop\ProductController::class, 'update'])->name('products.update');
        Route::delete('products/{product}', [\App\Http\Controllers\Shop\ProductController::class, 'destroy'])->name('products.destroy');

        // Variations (under Products)
        Route::get('variations/export', [\App\Http\Controllers\Shop\VariationController::class, 'export'])->name('variations.export');
        Route::resource('variations', \App\Http\Controllers\Shop\VariationController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        // Remaining routes will be added per module
        Route::get('/{any}', fn () => Inertia::render('Shop/Dashboard'))
            ->where('any', '.*')
            ->name('catch-all');
    });

// Root redirect
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('shop.users.index');
    }

    return redirect()->route('login');
});
