<?php

/**
 * Simple PSR-4-like autoloader so the project runs without Composer.
 * Namespace "App\" maps to the /src directory.
 */
spl_autoload_register(function (string $class) {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $relativePath = str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    $file = __DIR__ . '/../' . $relativePath;
    if (file_exists($file)) {
        require $file;
    }
});
