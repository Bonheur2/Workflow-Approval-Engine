<?php

/**
 * Points this frontend at your backend API. Change API_BASE_URL if your
 * backend runs on a different host/port.
 */

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

define('API_BASE_URL', getenv('API_BASE_URL') ?: 'http://localhost:8000/api');
