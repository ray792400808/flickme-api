<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\RequireAdminPermission;
use Illuminate\Support\Facades\Route;

Route::get('/health', [SystemController::class, 'health'])->name('health');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('auth.login');
Route::post('/auth/activate', [AuthController::class, 'activate'])->middleware('throttle:10,1')->name('auth.activate');

Route::middleware(AuthenticateAdmin::class)->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::middleware(RequireAdminPermission::class.':system,r')->group(function (): void {
        Route::get('/system/info', [SystemController::class, 'info'])->name('system.info');
        Route::get('/accounts', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::get('/audit-logs', [AccountController::class, 'logs'])->name('logs.index');
    });
    Route::middleware(RequireAdminPermission::class.':system,rw')->group(function (): void {
        Route::post('/accounts', [AccountController::class, 'store'])->name('accounts.store');
        Route::patch('/accounts/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::delete('/accounts/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
        Route::post('/accounts/{account}/reset-password', [AccountController::class, 'resetPassword'])->name('accounts.reset');
        Route::patch('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });
});
