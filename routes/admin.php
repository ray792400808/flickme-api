<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CoinAdjustmentController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\Admin\SystemController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Middleware\AuthenticateAdmin;
use App\Http\Middleware\RequireAdminPermission;
use Illuminate\Support\Facades\Route;

Route::get('/health', [SystemController::class, 'health'])->name('health');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('auth.login');

Route::middleware(AuthenticateAdmin::class)->group(function (): void {
    Route::get('/tags', [ContentController::class, 'tags'])->name('tags.index');
    Route::get('/tags/{tag}', [ContentController::class, 'tagDetail'])->whereNumber('tag')->name('tags.show');
    Route::get('/dramas', [ContentController::class, 'dramas'])->name('dramas.index');
    Route::get('/dramas/{drama}', [ContentController::class, 'drama'])->whereNumber('drama')->name('dramas.show');
    Route::get('/characters', [ContentController::class, 'characters'])->name('characters.index');
    Route::get('/characters/{character}', [ContentController::class, 'character'])->whereNumber('character')->name('characters.show');
    Route::middleware(RequireAdminPermission::class.':ops,r')->group(function (): void {
        Route::get('/ops/{resource}', [OperationsController::class, 'show']);
    });
    Route::middleware(RequireAdminPermission::class.':ops,rw')->group(function (): void {
        Route::put('/ops/{resource}', [OperationsController::class, 'update']);
        Route::post('/ops/{resource}/publish', [OperationsController::class, 'publish']);
    });
    Route::middleware(RequireAdminPermission::class.':users,r')->group(function (): void {
        Route::get('/users', [UserController::class, 'index']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::get('/coin-adjustments', [CoinAdjustmentController::class, 'index']);
    });
    Route::middleware(RequireAdminPermission::class.':users,rw')->group(function (): void {
        Route::patch('/users/{id}/status', [UserController::class, 'status']);
        Route::post('/coin-adjustments', [CoinAdjustmentController::class, 'store']);
    });
    Route::post('/coin-adjustments/{id}/review', [CoinAdjustmentController::class, 'review'])->whereNumber('id');
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
});
