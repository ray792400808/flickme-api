<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class SqlErrorHandler
{
    public function __invoke(array $config): Logger
    {
        $path = storage_path('logs/sql_err.log');
        $days = $config['days'] ?? 30;

        $formatter = new LineFormatter(
            "[%datetime%] [%channel%] [%level_name%] %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true,
            true
        );

        $handler = new RotatingFileHandler($path, $days, Logger::DEBUG);
        $handler->setFormatter($formatter);

        $logger = new Logger('sql_error');
        $logger->pushHandler($handler);

        return $logger;
    }
}
