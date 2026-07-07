<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Thin PDO wrapper. Supports sqlite (default, zero-config) and mysql.
 * Single shared connection per request (simple singleton).
 */
class Database
{
    private static ?PDO $instance = null;

    public static function connection(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $driver = Env::get('DB_DRIVER', 'sqlite');

        try {
            if ($driver === 'mysql') {
                $host = Env::get('DB_HOST', '127.0.0.1');
                $port = Env::get('DB_PORT', '3306');
                $name = Env::get('DB_DATABASE', 'workflow_engine');
                $user = Env::get('DB_USERNAME', 'root');
                $pass = Env::get('DB_PASSWORD', '');
                $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            } else {
                $path = Env::get('DB_PATH', __DIR__ . '/../../storage/database.sqlite');
                $pdo = new PDO('sqlite:' . $path, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
        } catch (PDOException $e) {
            Logger::error('Database connection failed: ' . $e->getMessage());
            throw new PDOException('Could not connect to the database.');
        }

        self::$instance = $pdo;
        return $pdo;
    }

    /** Allow tests to reset the shared connection (e.g. to point at a fresh in-memory db). */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
