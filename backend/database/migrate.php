<?php

/**
 * Applies SQL migration files to the configured database.
 * Usage: php database/migrate.php
 */

require __DIR__ . '/../src/Core/Autoloader.php';

use App\Core\Env;
use App\Core\Database;

Env::load(__DIR__ . '/../.env');

$driver = Env::get('DB_DRIVER', 'sqlite');
$dir = __DIR__ . "/migrations/$driver";

if (!is_dir($dir)) {
    fwrite(STDERR, "No migrations found for driver '$driver'.\n");
    exit(1);
}

// Make sure the storage directory exists for sqlite.
if ($driver === 'sqlite') {
    $storageDir = __DIR__ . '/../storage';
    if (!is_dir($storageDir)) {
        mkdir($storageDir, 0777, true);
    }
}

$pdo = Database::connection();
$files = glob("$dir/*.sql");
sort($files);

foreach ($files as $file) {
    echo "Applying migration: " . basename($file) . "\n";
    $sql = file_get_contents($file);
    // PDO::exec can run multiple statements for sqlite/mysql when separated by ';'
    $pdo->exec($sql);
}

echo "Migrations complete (" . count($files) . " file(s) applied) using driver '$driver'.\n";
