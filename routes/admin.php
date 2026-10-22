<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminMenuController;
use App\Http\Controllers\Admin\AdminOperationLogController;
use App\Http\Controllers\Admin\AdminPermissionController;
use App\Http\Controllers\Admin\AdminRoleController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\BadgeController;
use App\Http\Controllers\Admin\DramaController;
use App\Http\Controllers\Admin\IncentiveController;
use App\Http\Controllers\Admin\LanguageController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\TagController;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\LogAdminOperation;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 氓聬聨氓聫掳莽庐隆莽聬聠忙聨楼氓聫拢  /admin
|--------------------------------------------------------------------------
| 忙炉聫盲赂陋氓聠聶忙聯聧盲陆聹茅聝陆莽禄聭氓庐職盲赂聙盲赂陋忙聦聣茅聮庐莽潞搂忙聺聝茅聶聬莽聽聛茂录聢module.action茂录聣茂录聦
| 茂驴陆?admin_permissions 猫隆篓盲赂聙盲赂聙氓炉鹿氓潞聰茂录聦氓聣聧莽芦炉莽聰篓氓聬聦盲赂聙氓楼聴莽聽聛忙聨搂氓聢露忙聦聣茅聮庐忙聵戮茅職聬茂驴陆?*/

Route::get('/health', [SystemController::class, 'health'])->name('health');

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AdminAuthController::class, 'login'])->middleware('throttle:5,1')->name('auth.login');
});

Route::middleware([AuthenticateAdmin::class, LogAdminOperation::class])->group(function (): void {

    // ---------- 氓陆聯氓聣聧莽聶禄氓陆聲茂驴陆?----------
    Route::prefix('auth')->group(function (): void {
        Route::get('/me', [AdminAuthController::class, 'me'])->name('auth.me');
        Route::get('/menus', [AdminAuthController::class, 'menus'])->name('auth.menus');
        Route::post('/refresh', [AdminAuthController::class, 'refresh'])->name('auth.refresh');
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('auth.logout');
        Route::post('/change-password', [AdminAuthController::class, 'changePassword'])->name('auth.password');
    });

    Route::get('/session', [SystemController::class, 'session'])->name('session');
    foreach (['check-in-rewards' => 'checkin', 'tasks' => 'tasks', 'watch-rewards' => 'watch'] as $path => $resource) {
        Route::get('/'.$path, [IncentiveController::class, 'show'])->defaults('resource', $resource)
            ->middleware(CheckAdminPermission::class.':tasks.view')->name('incentive.'.$resource.'.show');
    }
    Route::put('/check-in-rewards', [IncentiveController::class, 'saveCheckin'])->middleware(CheckAdminPermission::class.':tasks.edit')->name('incentive.checkin.save');
    Route::put('/watch-rewards', [IncentiveController::class, 'saveWatch'])->middleware(CheckAdminPermission::class.':tasks.edit')->name('incentive.watch.save');
    Route::post('/tasks', [IncentiveController::class, 'storeTask'])->middleware(CheckAdminPermission::class.':tasks.edit')->name('incentive.tasks.store');
    Route::patch('/tasks/{task}', [IncentiveController::class, 'updateTask'])->middleware(CheckAdminPermission::class.':tasks.edit')->name('incentive.tasks.update');
    Route::delete('/tasks/{task}', [IncentiveController::class, 'destroyTask'])->middleware(CheckAdminPermission::class.':tasks.edit')->name('incentive.tasks.destroy');
    foreach (['check-in-rewards' => 'checkin', 'tasks' => 'tasks'] as $path => $resource) {
        Route::post('/'.$path.'/publish', [IncentiveController::class, 'publish'])->defaults('resource', $resource)
            ->middleware(CheckAdminPermission::class.':tasks.publish')->name('incentive.'.$resource.'.publish');
    }
    Route::prefix('ops')->name('ops.')->group(function (): void {
        Route::get('/{resource}', [OperationsController::class, 'show'])
            ->middleware(CheckAdminPermission::class.':ops.view')->name('show');
        Route::put('/{resource}', [OperationsController::class, 'update'])
            ->middleware(CheckAdminPermission::class.':ops.edit')->name('update');
        Route::post('/{resource}/publish', [OperationsController::class, 'publish'])
            ->middleware(CheckAdminPermission::class.':ops.publish')->name('publish');
    });
    Route::get('/system/info', [SystemController::class, 'info'])
        ->middleware(CheckAdminPermission::class.':system.view')->name('system.info');

    // ---------- 莽聰篓忙聢路莽庐隆莽聬聠 ----------
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

    // ---------- 猫搂聮猫聣虏莽庐隆莽聬聠 ----------
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

    // ---------- 忙聺聝茅聶聬莽庐隆莽聬聠 ----------
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

    // ---------- 猫聫聹氓聧聲莽庐隆莽聬聠 ----------
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

    // ---------- 忙聯聧盲陆聹忙聴楼氓驴聴 ----------
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

    // ---------- 璇█绠＄悊 ----------
    Route::prefix('languages')->name('languages.')->group(function (): void {
        Route::get('/', [LanguageController::class, 'index'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('index');
        Route::post('/', [LanguageController::class, 'store'])
            ->middleware(CheckAdminPermission::class.':content.create')->name('store');
        Route::get('/options', [LanguageController::class, 'options'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('options');
        Route::get('/{language}', [LanguageController::class, 'show'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('show');
        Route::patch('/{language}', [LanguageController::class, 'update'])
            ->middleware(CheckAdminPermission::class.':content.edit')->name('update');
        Route::delete('/{language}', [LanguageController::class, 'destroy'])
            ->middleware(CheckAdminPermission::class.':content.delete')->name('destroy');
    });

    // ---------- 瑙掓爣绠＄悊 ----------
    Route::prefix('badges')->name('badges.')->group(function (): void {
        Route::get('/', [BadgeController::class, 'index'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('index');
        Route::post('/', [BadgeController::class, 'store'])
            ->middleware(CheckAdminPermission::class.':content.create')->name('store');
        Route::get('/options', [BadgeController::class, 'options'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('options');
        Route::get('/{badge}', [BadgeController::class, 'show'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('show');
        Route::patch('/{badge}', [BadgeController::class, 'update'])
            ->middleware(CheckAdminPermission::class.':content.edit')->name('update');
        Route::delete('/{badge}', [BadgeController::class, 'destroy'])
            ->middleware(CheckAdminPermission::class.':content.delete')->name('destroy');
    });

    // ---------- 鏍囩涓庡悎闆?----------
    Route::prefix('tags')->name('tags.')->group(function (): void {
        Route::get('/', [TagController::class, 'index'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('index');
        Route::post('/', [TagController::class, 'store'])
            ->middleware(CheckAdminPermission::class.':content.create')->name('store');
        Route::get('/options', [TagController::class, 'options'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('options');
        Route::get('/stats', [TagController::class, 'stats'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('stats');
        Route::get('/{tag}', [TagController::class, 'show'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('show');
        Route::patch('/{tag}', [TagController::class, 'update'])
            ->middleware(CheckAdminPermission::class.':content.edit')->name('update');
        Route::delete('/{tag}', [TagController::class, 'destroy'])
            ->middleware(CheckAdminPermission::class.':content.delete')->name('destroy');
    });

    // ---------- 鍓у簱 ----------
    Route::prefix('dramas')->name('dramas.')->group(function (): void {
        Route::get('/', [DramaController::class, 'index'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('index');
        Route::post('/', [DramaController::class, 'store'])
            ->middleware(CheckAdminPermission::class.':content.create')->name('store');
        Route::get('/{drama}', [DramaController::class, 'show'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('show');
        Route::patch('/{drama}', [DramaController::class, 'update'])
            ->middleware(CheckAdminPermission::class.':content.edit')->name('update');
        Route::delete('/{drama}', [DramaController::class, 'destroy'])
            ->middleware(CheckAdminPermission::class.':content.delete')->name('destroy');
        Route::get('/{drama}/episodes', [DramaController::class, 'episodes'])
            ->middleware(CheckAdminPermission::class.':content.view')->name('episodes');
        Route::post('/{drama}/episodes', [DramaController::class, 'storeEpisode'])
            ->middleware(CheckAdminPermission::class.':content.create')->name('store-episode');
        Route::patch('/{drama}/episodes/{episode}', [DramaController::class, 'updateEpisode'])
            ->middleware(CheckAdminPermission::class.':content.edit')->name('update-episode');
        Route::delete('/{drama}/episodes/{episode}', [DramaController::class, 'destroyEpisode'])
            ->middleware(CheckAdminPermission::class.':content.delete')->name('destroy-episode');
    });

    // 旧版内容库只读接口（tags.key / 字符串 status）已迁移到 routes/admin_legacy.php,
    // 且只挂载在 /admin/v1 前缀下,避免与新的内容中台接口(/admin)同名 URI 互相覆盖.
});
