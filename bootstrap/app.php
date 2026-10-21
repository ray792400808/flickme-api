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
