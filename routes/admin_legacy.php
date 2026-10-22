<?php

use App\Http\Controllers\Admin\ContentController;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\CheckAdminPermission;
use App\Http\Middleware\LogAdminOperation;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| 旧版内容库兼容路由（仅挂载在 /admin/v1 下）
|--------------------------------------------------------------------------
|
| 早期后台把内容库接口挂在 /admin/v1 下，字段契约沿用旧结构：
|   - tags.key      （新结构为 tags.code）
|   - tags.status   （旧为 active/disabled 字符串，新为 1/2）
|   - dramas.status （旧为 published/draft/unpublished 字符串，新为 0/1/2）
|
| 新的内容中台接口（剧库 / 标签与合集 / 角标 / 语言列表）统一挂在 /admin，
| 由 routes/admin.php 提供；两者不要混在同一个前缀下，否则同名 URI 会
| 互相覆盖（RouteCollection 按 method+uri 唯一键存储，后注册的覆盖先注册的）。
|
*/

Route::middleware([AuthenticateAdmin::class, LogAdminOperation::class])
    ->group(function (): void {
        Route::middleware(CheckAdminPermission::class.':content.view')->group(function (): void {
            Route::get('/tags', [ContentController::class, 'tags'])->name('legacy.tags');
            Route::get('/tags/{tag}', [ContentController::class, 'tagDetail'])->name('legacy.tags.show');
            Route::get('/dramas', [ContentController::class, 'dramas'])->name('legacy.dramas');
            Route::get('/dramas/{drama}', [ContentController::class, 'drama'])->name('legacy.dramas.show');
            Route::get('/characters', [ContentController::class, 'characters'])->name('legacy.characters');
            Route::get('/characters/{character}', [ContentController::class, 'character'])->name('legacy.characters.show');
        });
    });
