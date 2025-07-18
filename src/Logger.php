<?php
namespace App;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private static $logger = null;

    public static function getInstance()
    {
        if (self::$logger === null) {
            self::$logger = new MonologLogger('drepadata');
            
            // Create logs directory if it doesn't exist
            $logDir = __DIR__ . '/../logs';
            if (!is_dir($logDir)) {
                mkdir($logDir, 0755, true);
            }
            
            $handler = new StreamHandler($logDir . '/error.log', MonologLogger::ERROR);
            $formatter = new LineFormatter(
                "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
                'Y-m-d H:i:s'
            );
            $handler->setFormatter($formatter);
            
            self::$logger->pushHandler($handler);
        }
        
        return self::$logger;
    }

    public static function error($message, $context = [])
    {
        self::getInstance()->error($message, $context);
    }

    public static function warning($message, $context = [])
    {
        self::getInstance()->warning($message, $context);
    }

    public static function info($message, $context = [])
    {
        self::getInstance()->info($message, $context);
    }
}