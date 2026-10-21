<?php

namespace App\Providers;

use App\Exceptions\Handler;
use App\Services\UnixFailedJobProvider;
use App\Services\UnixPasswordBrokerManager;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->register(PasswordResetServiceProvider::class);
        $this->app->singleton('auth.password', fn (Application $app): UnixPasswordBrokerManager => new UnixPasswordBrokerManager($app));
        $this->app->register(QueueServiceProvider::class);
        $this->app->extend('queue.failer', function (mixed $provider, Application $app): mixed {
            if ($provider instanceof DatabaseUuidFailedJobProvider) {
                $config = $app['config']['queue.failed'];

                return new UnixFailedJobProvider($app['db'], $config['database'], $config['table']);
            }

            return $provider;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('active-users', function (Application $app, array $config): EloquentUserProvider {
            return (new EloquentUserProvider($app['hash'], $config['model']))
                ->withQuery(fn (Builder $query): Builder => $query->where('status', 'active'));
        });

        // 注册SQL错误监听器
        if (config('app.debug') || config('app.sql_log', false)) {
            $this->registerSqlErrorListener();
        }
    }

    /**
     * 注册SQL错误监听器
     */
    private function registerSqlErrorListener(): void
    {
        DB::listen(function ($query): void {
            // 如果是错误查询（例如连接失败），$query 是 Throwable
            if ($query instanceof \Throwable) {
                $this->logSqlError($query);
                return;
            }

            // 检查是否是慢查询或查询错误
            if (config('app.sql_log', false) && $query->time > config('app.sql_slow_threshold', 1000)) {
                $this->logSlowQuery($query);
            }
        });
    }

    /**
     * 记录SQL错误
     */
    private function logSqlError(\Throwable $e): void
    {
        $traceId = $this->getCurrentTraceId();

        Log::channel('sql_error')->error("[{$traceId}] " . $e->getMessage(), [
            'code' => $e->getCode(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    /**
     * 记录慢查询
     */
    private function logSlowQuery($query): void
    {
        $traceId = $this->getCurrentTraceId();

        $sql = $query->sql;
        $bindings = $query->bindings;
        $time = $query->time;

        // 替换占位符
        foreach ($bindings as $binding) {
            $value = is_numeric($binding) ? $binding : "'" . addslashes((string) $binding) . "'";
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        Log::channel('sql_slow')->warning("[{$traceId}] Slow query [{$time}ms]: {$sql}");
    }

    /**
     * 获取当前请求的 trace_id
     */
    private function getCurrentTraceId(): string
    {
        try {
            $request = request();
            if ($request) {
                return $request->attributes->get('trace_id', 'N/A');
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return 'N/A';
    }
}
