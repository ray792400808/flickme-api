<?php

namespace App\Services;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Auth\Passwords\TokenRepositoryInterface;

class UnixPasswordBrokerManager extends PasswordBrokerManager
{
    protected function createTokenRepository(array $config): TokenRepositoryInterface
    {
        if (($config['driver'] ?? null) === 'cache') {
            return parent::createTokenRepository($config);
        }
        $key = $this->app['config']['app.key'];
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return new UnixPasswordTokenRepository($this->app['db']->connection($config['connection'] ?? null), $this->app['hash'], $config['table'], $key, ($config['expire'] ?? 60) * 60, $config['throttle'] ?? 0);
    }
}
