<?php

namespace Tests;

use App\Core\Database;
use PDO;

/**
 * A deliberately tiny test framework - no PHPUnit, since Composer/Packagist
 * isn't reachable in restricted network environments and this project has
 * a "pure PHP, no dependencies" mandate. Each test file exposes public
 * methods; tests/run.php discovers and executes them, reporting
 * pass/fail counts and any assertion failures.
 */
abstract class TestCase
{
    protected array $failures = [];
    protected int $assertions = 0;

    /** Fresh in-memory SQLite database, schema applied, per test method. */
    protected function freshDatabase(): void
    {
        Database::reset();
        putenv('DB_DRIVER=sqlite');
        putenv('DB_PATH=:memory:');
        $pdo = Database::connection();
        $schema = file_get_contents(__DIR__ . '/../database/migrations/sqlite/001_create_tables.sql');
        $pdo->exec($schema);
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertions++;
        if (!$condition) {
            $this->failures[] = $message ?: 'Expected true, got false.';
        }
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertTrue(!$condition, $message ?: 'Expected false, got true.');
    }

    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected != $actual) {
            $expectedStr = is_scalar($expected) ? (string) $expected : json_encode($expected);
            $actualStr = is_scalar($actual) ? (string) $actual : json_encode($actual);
            $this->failures[] = $message ?: "Expected [$expectedStr], got [$actualStr].";
        }
    }

    protected function assertNull($value, string $message = ''): void
    {
        $this->assertTrue($value === null, $message ?: 'Expected null.');
    }

    protected function assertNotNull($value, string $message = ''): void
    {
        $this->assertTrue($value !== null, $message ?: 'Expected non-null value.');
    }

    protected function assertThrows(callable $fn, string $message = ''): void
    {
        $this->assertions++;
        try {
            $fn();
            $this->failures[] = $message ?: 'Expected an exception to be thrown, none was.';
        } catch (\Throwable $e) {
            // expected
        }
    }

    /** Runs every public test*() method on this class; returns [passed, failed, messages]. */
    public function run(): array
    {
        $methods = array_filter(get_class_methods($this), fn($m) => str_starts_with($m, 'test'));
        $results = [];
        foreach ($methods as $method) {
            $this->failures = [];
            $this->assertions = 0;
            try {
                $this->$method();
            } catch (\Throwable $e) {
                $this->failures[] = 'Uncaught exception: ' . $e->getMessage();
            }
            $results[$method] = [
                'passed' => empty($this->failures),
                'assertions' => $this->assertions,
                'failures' => $this->failures,
            ];
        }
        return $results;
    }
}
