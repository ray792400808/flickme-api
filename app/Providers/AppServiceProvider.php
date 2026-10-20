<?php

namespace App\Providers;

use App\Services\UnixFailedJobProvider;
use App\Services\UnixPasswordBrokerManager;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Auth\Passwords\PasswordResetServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Queue\Failed\DatabaseUuidFailedJobProvider;
use Illuminate\Queue\QueueServiceProvider;
use Illuminate\Support\Facades\Auth;
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
    }
}
