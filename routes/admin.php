<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminMenuController;
use App\Http\Controllers\Admin\AdminOperationLogController;
use App\Http\Controllers\Admin\AdminPermissionController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\LogAdminOperation;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 后台管理接口  /admin
|--------------------------------------------------------------------------
| 每个写操作都绑定一个按钮级权限码（module.action），
| �?admin_permissions 表一一对应，前端用同一套码控制按钮显隐�?*/

Route::get('/health', [SystemController::class, 'health'])->name('health');

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('auth.login');
});

Route::middleware([AuthenticateAdmin::class, LogAdminOperation::class])->group(function (): void {

    // ---------- 当前登录�?----------
    Route::prefix('auth')->group(function (): void {
        Route::get('/me', [AdminAuthController::class, 'me'])->name('auth.me');
        Route::get('/menus', [AdminAuthController::class, 'menus'])->name('auth.menus');
        Route::post('/refresh', [AdminAuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('auth.logout');
        Route::post('/change-password', [AdminAuthController::class, 'changePassword'])->name('auth.password');
    });

    Route::get('/session', [SystemController::class, 'session'])->name('session');
    Route::get('/system/info', [SystemController::class, 'info'])
        ->middleware(CheckAdminPermission::class.':system.view')->name('system.info');

    // ---------- 用户管理 ----------
    Route::prefix('users')->name('users.')->group(function (): void {
        Route::get('/', [AdminUserController::class, 'index'])
            ->middleware(CheckAdminPermission::class.':user.view')->name('index');
        Route::post('/', [AdminUserController::class, 'store'])
            ->middleware(CheckAdminPermission::class.':user.create')->name('store');
        Route::get('/{user}', [AdminUserController::class, 'show'])
            ->middleware(CheckAdminPermission::class.':user.view')->name('show');
        Route::patch('/{user}', [AdminUserController::class, 'update'])
            ->middleware(CheckAdminPermission::class.':user.edit')->name('update');
        Route::delete('/{user}', [AdminUserController::class, 'destroy'])
            ->middleware(CheckAdminPermission::class.':user.delete')->name('destroy');
        Route::post('/{user}/reset-password', [AdminUserController::class, 'resetPassword'])
            ->middleware(CheckAdminPermission::class.':user.edit')->name('reset-password');
        Route::post('/{user}/force-logout', [AdminUserController::class, 'forceLogout'])
            ->middleware(CheckAdminPermission::class.':user.edit')->name('force-logout');
    });

    // ---------- 角色管理 ----------
    Route::prefix('roles')->name('roles.')->group(function (): void {
        Route::get('/', [AdminRoleController::class, 'index'])
            ->middleware(CheckAdminPermission::class.':role.view')->name('index');
        Route::post('/', [AdminRoleController::class, 'store'])
            ->middleware(CheckAdminPermission::class.':role.create')->name('store');
        Route::get('/{role}', [AdminRoleController::class, 'show'])
            ->middleware(CheckAdminPermission::class.':role.view')->name('show');
        Route::patch('/{role}', [AdminRoleController::class, 'update'])
            ->middleware(CheckAdminPermission::class.':role.edit')->name('update');
        Route::delete('/{role}', [AdminRoleController::class, 'destroy'])
            ->middleware(CheckAdminPermission::class.':role.delete')->name('destroy');
        Route::get('/{role}/users', [AdminRoleController::class, 'users'])
            ->middleware(CheckAdminPermission::class.':role.view')->name('users');
    });

    // ---------- 权限管理 ----------
    Route::prefix('permissions')->name('permissions.')->group(function (): void {
        Route::get('/', [AdminPermissionController::class, 'index'])
            ->middleware(CheckAdminPermission::class.':permission.view')->name('index');
        Route::get('/mine', [AdminPermissionController::class, 'mine'])->name('mine');
        Route::get('/codes', [AdminPermissionController::class, 'codes'])
            ->middleware(CheckAdminPermission::class.':permission.view')->name('codes');
        Route::post('/batch', [AdminPermissionController::class, 'batch'])
            ->middleware(CheckAdminPermission::class.':permission.create')->name('batch');
        Route::post('/', [AdminPermissionController::class, 'store'])
            ->middleware(CheckAdminPermission::class.':permission.create')->name('store');
        Route::get('/{permission}', [AdminPermissionController::class, 'show'])
            ->middleware(CheckAdminPermission::class.':permission.view')->name('show');
        Route::patch('/{permission}', [AdminPermissionController::class, 'update'])
            ->middleware(CheckAdminPermission::class.':permission.edit')->name('update');
        Route::delete('/{permission}', [AdminPermissionController::class, 'destroy'])
            ->middleware(CheckAdminPermission::class.':permission.delete')->name('destroy');
    });

    // ---------- 菜单管理 ----------
    Route::prefix('menus')->name('menus.')->group(function (): void {
        Route::get('/', [AdminMenuController::class, 'index'])
            ->middleware(CheckAdminPermission::class.':menu.view')->name('index');
        Route::get('/options', [AdminMenuController::class, 'options'])
            ->middleware(CheckAdminPermission::class.':menu.view')->name('options');
        Route::post('/', [AdminMenuController::class, 'store'])
            ->middleware(CheckAdminPermission::class.':menu.create')->name('store');
        Route::post('/sort', [AdminMenuController::class, 'sort'])
            ->middleware(CheckAdminPermission::class.':menu.edit')->name('sort');
        Route::get('/{menu}', [AdminMenuController::class, 'show'])
            ->middleware(CheckAdminPermission::class.':menu.view')->name('show');
        Route::patch('/{menu}', [AdminMenuController::class, 'update'])
            ->middleware(CheckAdminPermission::class.':menu.edit')->name('update');
        Route::delete('/{menu}', [AdminMenuController::class, 'destroy'])
            ->middleware(CheckAdminPermission::class.':menu.delete')->name('destroy');
    });

    // ---------- 操作日志 ----------
    Route::prefix('operation-logs')->name('logs.')->group(function (): void {
        Route::get('/', [AdminOperationLogController::class, 'index'])
            ->middleware(CheckAdminPermission::class.':log.view')->name('index');
        Route::post('/purge', [AdminOperationLogController::class, 'purge'])
            ->middleware(CheckAdminPermission::class.':log.delete')->name('purge');
        Route::get('/{log}', [AdminOperationLogController::class, 'show'])
            ->middleware(CheckAdminPermission::class.':log.view')->name('show');
        Route::delete('/{log}', [AdminOperationLogController::class, 'destroy'])
            ->middleware(CheckAdminPermission::class.':log.delete')->name('destroy');
    });
});
