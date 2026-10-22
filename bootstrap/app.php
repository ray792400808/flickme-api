<?php

use App\Exceptions\Handler;
use App\Http\Middleware\RequestLogMiddleware;
use App\Http\Middleware\TraceIdMiddleware;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('api')
                ->prefix('admin')
                ->name('admin.api.')
                ->group(base_path('routes/admin.php'));

            // 兼容旧版后台：早期后台接口挂在 /admin/v1 下，
            // 前端已切到 /admin，但历史脚本与测试仍走 /admin/v1。
            // 旧版内容库只读接口（tags.key / 字符串 status）单独放在 admin_legacy.php，
            // 并且必须后注册，才能覆盖同名的新接口（RouteCollection 按 method+uri 唯一键存储）。
            Route::middleware('api')
                ->prefix('admin/v1')
                ->name('admin.v1.api.')
                ->group(base_path('routes/admin.php'));

            Route::middleware('api')
                ->prefix('admin/v1')
                ->name('admin.v1.api.')
                ->group(base_path('routes/admin_legacy.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->convertEmptyStringsToNull(except: [fn (Request $request): bool => $request->is('admin/ops/*', 'admin/v1/ops/*')]);
        $middleware->trimStrings(except: [fn (Request $request): bool => $request->is('admin/ops/*', 'admin/v1/ops/*')]);

        // 全局中间件
        $middleware->api(prepend: [
            TraceIdMiddleware::class,
            RequestLogMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('v1/*', 'admin/*') || $request->expectsJson(),
        );
    })
    ->withSingletons([
        ExceptionHandler::class => Handler::class,
    ])->create();
