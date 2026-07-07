<?php

/**
 * Runs every Tests\*Test class in this directory and prints a summary.
 * Usage: php tests/run.php
 * Exits with code 1 if any assertion failed (useful for CI).
 */

require __DIR__ . '/../src/Core/Autoloader.php';
require __DIR__ . '/TestCase.php';

use App\Core\Env;

Env::load(__DIR__ . '/../.env');

spl_autoload_register(function ($class) {
    $prefix = 'Tests\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/' . substr($class, strlen($prefix)) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    }
});

$files = glob(__DIR__ . '/*Test.php');
$totalPassed = 0;
$totalFailed = 0;
$totalAssertions = 0;

foreach ($files as $file) {
    $class = 'Tests\\' . basename($file, '.php');
    if (!class_exists($class)) {
        continue;
    }
    echo "=== $class ===\n";
    $instance = new $class();
    $results = $instance->run();

    foreach ($results as $method => $result) {
        $totalAssertions += $result['assertions'];
        if ($result['passed']) {
            $totalPassed++;
            echo "  [PASS] $method ({$result['assertions']} assertions)\n";
        } else {
            $totalFailed++;
            echo "  [FAIL] $method\n";
            foreach ($result['failures'] as $failure) {
                echo "         - $failure\n";
            }
        }
    }
}

echo "\n----------------------------------------\n";
echo "Tests passed: $totalPassed, failed: $totalFailed, total assertions: $totalAssertions\n";

exit($totalFailed > 0 ? 1 : 0);
