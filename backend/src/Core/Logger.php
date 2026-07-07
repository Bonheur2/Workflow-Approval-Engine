<?php

namespace App\Core;

/**
 * Minimal file-based logger. Writes structured, line-based log entries.
 * Kept dependency-free on purpose (no Monolog etc.) per the "pure PHP" requirement.
 */
class Logger
{
    private static function path(): string
    {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        return $dir . '/app.log';
    }

    private static function write(string $level, string $message, array $context = []): void
    {
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($level),
            $message,
            empty($context) ? '' : json_encode($context, JSON_UNESCAPED_SLASHES)
        );
        @file_put_contents(self::path(), $line, FILE_APPEND | LOCK_EX);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }
}
