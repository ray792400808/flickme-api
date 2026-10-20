<?php

use App\Http\Controllers\Api\AppController;
use Illuminate\Support\Facades\Route;

Route::get('/health', [AppController::class, 'health'])->name('api.health');
Route::get('/app/config', [AppController::class, 'config'])->name('api.app.config');
Route::get('/app/locales', [AppController::class, 'locales'])->name('api.app.locales');
Route::get('/server/time', [AppController::class, 'serverTime'])->name('api.server.time');
