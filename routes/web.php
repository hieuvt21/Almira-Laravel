<?php

use App\Http\Controllers\AppearanceController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\CustomerExchangeController;
use App\Http\Controllers\CustomerTierController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StoreSettingsController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Vào app: chưa đăng nhập -> trang đăng nhập, đã đăng nhập -> dashboard
Route::get('/', function () {
    $user = auth()->user();

    return $user
        ? redirect()->route($user->homeRoute())
        : redirect()->route('login');
})->name('home');

Route::middleware('auth')->group(function () {
    // Trang chung cho mọi tài khoản sau đăng nhập (báo cáo, thông tin tổng quan)
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Giao diện: ai cũng xem được; lưu thương hiệu cửa hàng cần settings.manage
    Route::get('/appearance', [AppearanceController::class, 'edit'])->name('appearance.edit');
    Route::post('/appearance/branding', [AppearanceController::class, 'updateBranding'])
        ->middleware('can:settings.manage')->name('appearance.branding');

    // Cửa hàng: thông tin, tiền tệ & thuế, đánh số chứng từ
    Route::middleware('can:settings.manage')->prefix('store')->name('store.')->group(function () {
        Route::get('/', [StoreSettingsController::class, 'edit'])->name('edit');
        Route::put('/info', [StoreSettingsController::class, 'updateInfo'])->name('info');
        Route::put('/finance', [StoreSettingsController::class, 'updateFinance'])->name('finance');
        Route::put('/numbering', [StoreSettingsController::class, 'updateNumbering'])->name('numbering');
    });

    // Hạng khách hàng: mức chi tiêu và % giảm giá
    Route::middleware('can:settings.manage')->prefix('customer-tiers')->name('customer-tiers.')->group(function () {
        Route::get('/', [CustomerTierController::class, 'edit'])->name('edit');
        Route::put('/', [CustomerTierController::class, 'update'])->name('update');
    });

    // Người dùng
    Route::get('/users/check', [UserController::class, 'check'])->middleware('throttle:90,1')->name('users.check');
    Route::get('/users', [UserController::class, 'index'])->middleware('can:users.view')->name('users.index');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:users.create')->name('users.store');
    Route::put('/users/{user}', [UserController::class, 'update'])->middleware('can:users.update')->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('can:users.delete')->name('users.destroy');

    // Vai trò & quyền
    Route::middleware('can:roles.manage')->prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RoleController::class, 'index'])->name('index');
        Route::post('/', [RoleController::class, 'store'])->name('store');
        Route::put('/{role}', [RoleController::class, 'update'])->name('update');
        Route::delete('/{role}', [RoleController::class, 'destroy'])->name('destroy');
    });

    // Khách hàng (các route cố định phải đứng trước route có {customer})
    Route::get('/customers/check', [CustomerController::class, 'check'])
        ->middleware(['can:customers.view', 'throttle:90,1'])->name('customers.check');
    Route::get('/customers/export', [CustomerExchangeController::class, 'export'])
        ->middleware('role:admin')->name('customers.export');
    Route::get('/customers/template', [CustomerExchangeController::class, 'template'])
        ->middleware('role:admin')->name('customers.template');
    Route::post('/customers/import', [CustomerExchangeController::class, 'import'])
        ->middleware(['role:admin', 'throttle:10,1'])->name('customers.import');
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('can:customers.view')->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('can:customers.view')->name('customers.show');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('can:customers.create')->name('customers.store');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('can:customers.update')->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('can:customers.delete')->name('customers.destroy');
});

require __DIR__.'/auth.php';
