<?php

use App\Http\Controllers\AdminAuditController;
use App\Http\Controllers\AdminAuthController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\AdminShopController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\OwnerDashboardController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\PosScannerController;
use App\Http\Controllers\ScannerDeviceController;
use App\Http\Controllers\WarehouseDashboardController;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public (landing)
|--------------------------------------------------------------------------
*/

Route::get('/', [LandingController::class, 'index'])
    ->middleware('throttle:landing')
    ->name('landing');

Route::post('/ariza', [LandingController::class, 'storeApplication'])
    ->middleware('throttle:applications')
    ->name('applications.store');

Route::get('/til/{locale}', [LandingController::class, 'switchLocale'])
    ->where('locale', 'uz|ru')
    ->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Scanner device (telefon, public — token bilan himoyalangan)
|--------------------------------------------------------------------------
*/

Route::prefix('scanner/{token}')
    ->where(['token' => '[a-f0-9]{64}'])
    ->middleware('throttle:scanner-public')
    ->group(function () {
        Route::get('/', [ScannerDeviceController::class, 'show'])
            ->name('scanner.device');
        Route::post('/connect', [ScannerDeviceController::class, 'connect'])
            ->name('scanner.connect');
        Route::post('/scan', [ScannerDeviceController::class, 'scan'])
            ->name('scanner.scan');
        Route::get('/search', [ScannerDeviceController::class, 'search'])
            ->name('scanner.search');
    });

/*
|--------------------------------------------------------------------------
| Mijoz auth (owner / warehouse / cashier)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:customer-login')
        ->name('login.submit');

    Route::get('/forgot-password', [AuthController::class, 'showForgotPasswordForm'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendPasswordResetLink'])
        ->middleware('throttle:password-reset')
        ->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showResetPasswordForm'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])
        ->middleware('throttle:password-reset')
        ->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/change-password', [AuthController::class, 'showChangePasswordForm'])->name('password.change');
    Route::post('/change-password', [AuthController::class, 'changePassword'])
        ->middleware('throttle:change-password')
        ->name('password.change.submit');

    // Do'kon egasi (faol do'kon + parol o'zgartirilgan bo'lishi shart)
    Route::middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_OWNER])->group(function () {
        Route::get('/dashboard', [OwnerDashboardController::class, 'index'])
            ->name('owner.dashboard');

        Route::get('/dashboard/employees', [OwnerDashboardController::class, 'employees'])
            ->name('owner.employees.index');

        Route::get('/dashboard/reports', [OwnerDashboardController::class, 'reports'])
            ->name('owner.reports.index');

        Route::get('/dashboard/reports/print', [OwnerDashboardController::class, 'reportPrint'])
            ->name('owner.reports.print');

        Route::get('/dashboard/reports/export', [OwnerDashboardController::class, 'exportExcel'])
            ->name('owner.reports.export');

        Route::get('/dashboard/activity', [OwnerDashboardController::class, 'activity'])
            ->name('owner.activity.index');

        Route::get('/dashboard/sales', [OwnerDashboardController::class, 'sales'])
            ->name('owner.sales.index');

        Route::post('/dashboard/sales/settings', [OwnerDashboardController::class, 'updateVoidSetting'])
            ->middleware('throttle:owner-actions')
            ->name('owner.sales.settings');

        Route::get('/dashboard/sales/{sale}', [OwnerDashboardController::class, 'saleShow'])
            ->name('owner.sales.show');

        Route::post('/dashboard/sales/{sale}/void', [OwnerDashboardController::class, 'voidSale'])
            ->middleware('throttle:owner-actions')
            ->name('owner.sales.void');

        Route::get('/dashboard/inventory', [OwnerDashboardController::class, 'inventory'])
            ->name('owner.inventory.index');

        Route::post('/dashboard/employees/{employee}', [OwnerDashboardController::class, 'updateEmployee'])
            ->middleware('throttle:owner-actions')
            ->name('owner.employees.update');

        Route::post('/dashboard/employees/{employee}/toggle', [OwnerDashboardController::class, 'toggleEmployee'])
            ->middleware('throttle:owner-actions')
            ->name('owner.employees.toggle');

        Route::post('/employees', [OwnerDashboardController::class, 'storeEmployee'])
            ->middleware('throttle:owner-actions')
            ->name('owner.employees.store');

        Route::post('/employees/{employee}/reset-password', [OwnerDashboardController::class, 'regeneratePassword'])
            ->middleware('throttle:owner-actions')
            ->name('owner.employees.reset-password');
    });

    // Skladchi: dashboard, mahsulot va kirimlar
    Route::get('/warehouse', [WarehouseDashboardController::class, 'index'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_WAREHOUSE])
        ->name('warehouse.dashboard');

    Route::post('/warehouse/products', [WarehouseDashboardController::class, 'storeProduct'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_WAREHOUSE, 'throttle:warehouse-actions'])
        ->name('warehouse.products.store');

    Route::get('/warehouse/products/{product}', [WarehouseDashboardController::class, 'showProduct'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_WAREHOUSE, 'throttle:search'])
        ->name('warehouse.products.show');

    Route::get('/warehouse/products/{product}/print', [WarehouseDashboardController::class, 'printBarcodes'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_WAREHOUSE])
        ->name('warehouse.products.print');

    Route::post('/warehouse/products/{product}', [WarehouseDashboardController::class, 'updateProduct'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_WAREHOUSE, 'throttle:warehouse-actions'])
        ->name('warehouse.products.update');

    Route::post('/warehouse/receipts', [WarehouseDashboardController::class, 'storeReceipt'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_WAREHOUSE, 'throttle:warehouse-actions'])
        ->name('warehouse.receipts.store');

    Route::get('/warehouse/receipts/create', [WarehouseDashboardController::class, 'createReceipt'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_WAREHOUSE])
        ->name('warehouse.receipts.create');

    Route::get('/warehouse/variants', [WarehouseDashboardController::class, 'searchVariants'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_WAREHOUSE, 'throttle:search'])
        ->name('warehouse.variants.search');

    Route::get('/warehouse/receipts/{receipt}', [WarehouseDashboardController::class, 'showReceipt'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_WAREHOUSE])
        ->name('warehouse.receipts.show');

    Route::post('/warehouse/receipts/{receipt}/cancel', [WarehouseDashboardController::class, 'cancelReceipt'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_WAREHOUSE, 'throttle:warehouse-actions'])
        ->name('warehouse.receipts.cancel');

    // Kassir: POS (qidiruv + sotuv)
    Route::get('/pos', [PosController::class, 'index'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_CASHIER])
        ->name('pos.index');

    Route::get('/pos/catalog', [PosController::class, 'catalog'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_CASHIER, 'throttle:search'])
        ->name('pos.catalog');

    Route::post('/pos/sales', [PosController::class, 'storeSale'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_CASHIER, 'throttle:pos-sales'])
        ->name('pos.sales.store');

    Route::get('/pos/sales', [PosController::class, 'salesIndex'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_CASHIER])
        ->name('pos.sales.index');

    Route::get('/pos/sales/{sale}', [PosController::class, 'showSale'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_CASHIER])
        ->name('pos.sales.show');

    Route::post('/pos/sales/{sale}/void', [PosController::class, 'voidSale'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_CASHIER, 'throttle:pos-void'])
        ->name('pos.sales.void');

    // POS Scanner (kassir sessiyasi)
    Route::post('/pos/scanner', [PosScannerController::class, 'store'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_CASHIER, 'throttle:pos-scanner'])
        ->name('pos.scanner.store');

    Route::get('/pos/scanner/{session}/events', [PosScannerController::class, 'events'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_CASHIER, 'throttle:pos-scanner'])
        ->name('pos.scanner.events');

    Route::delete('/pos/scanner/{session}/close', [PosScannerController::class, 'close'])
        ->middleware(['shop.active', 'password.changed', 'role:'.User::ROLE_CASHIER, 'throttle:pos-scanner'])
        ->name('pos.scanner.close');
});

/*
|--------------------------------------------------------------------------
| Admin (Super Admin)
|--------------------------------------------------------------------------
*/

Route::middleware('guest-of-admin')->group(function () {
    Route::get('/admin/login', [AdminAuthController::class, 'showLoginForm'])
        ->name('admin.login');

    Route::post('/admin/login', [AdminAuthController::class, 'login'])
        ->middleware('throttle:admin-login')
        ->name('admin.login.submit');
});

Route::middleware(['admin.auth', 'throttle:admin'])->prefix('admin')->group(function () {
    Route::post('/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');

    Route::post('/applications/{application}/approve', [AdminDashboardController::class, 'approve'])
        ->name('admin.applications.approve');

    Route::post('/applications/{application}/reject', [AdminDashboardController::class, 'reject'])
        ->name('admin.applications.reject');

    Route::post('/applications/{application}/resend', [AdminDashboardController::class, 'resend'])
        ->name('admin.applications.resend');

    Route::get('/audit', [AdminAuditController::class, 'index'])->name('admin.audit.index');

    Route::get('/shops/{shop}', [AdminShopController::class, 'show'])->name('admin.shops.show');
    Route::post('/shops/{shop}', [AdminShopController::class, 'update'])->name('admin.shops.update');

    Route::post('/shops/{shop}/suspend', [AdminShopController::class, 'suspendShop'])
        ->name('admin.shops.suspend');

    Route::post('/shops/{shop}/activate', [AdminShopController::class, 'activateShop'])
        ->name('admin.shops.activate');

    Route::post('/shops/{shop}/users/{user}/toggle', [AdminShopController::class, 'toggleUser'])
        ->name('admin.shops.users.toggle');

    Route::post('/shops/{shop}/users/{user}/reset-password', [AdminShopController::class, 'resetUserPassword'])
        ->name('admin.shops.users.reset-password');

    Route::get('/shops/{shop}/products/{product}', [AdminShopController::class, 'editProduct'])
        ->whereNumber('product')
        ->name('admin.shops.products.edit');

    Route::post('/shops/{shop}/products/{product}', [AdminShopController::class, 'updateProduct'])
        ->whereNumber('product')
        ->name('admin.shops.products.update');

    Route::post('/shops/{shop}/sales/{sale}/void', [AdminShopController::class, 'voidSale'])
        ->whereNumber('sale')
        ->name('admin.shops.sales.void');

    Route::post('/shops/{shop}/variants/{variant}/adjust-stock', [AdminShopController::class, 'adjustStock'])
        ->whereNumber('variant')
        ->name('admin.shops.variants.adjust');
});

/*
|--------------------------------------------------------------------------
| Xato sahifalarini ko'rish uchun (faqat local/testing)
|--------------------------------------------------------------------------
*/

if (app()->environment('local', 'testing')) {
    Route::get('/test-401', fn () => abort(401));
    Route::get('/test-403', fn () => abort(403));
    Route::get('/test-419', fn () => abort(419));
    Route::get('/test-429', fn () => abort(429));
    Route::get('/test-500', fn () => abort(500));
    Route::get('/test-503', fn () => abort(503));
}
