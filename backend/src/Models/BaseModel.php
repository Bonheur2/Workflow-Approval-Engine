<?php

namespace App\Models;

use App\Core\Database;
use PDO;

abstract class BaseModel
{
    protected static string $table;

    protected static function db(): PDO
    {
        return Database::connection();
    }

    public static function find(int $id): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM ' . static::$table . ' WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        return static::db()->query('SELECT * FROM ' . static::$table)->fetchAll();
    }

    public static function delete(int $id): bool
    {
        $stmt = static::db()->prepare('DELETE FROM ' . static::$table . ' WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Generic insert helper. Keys become column names; values are bound
     * as parameters (never interpolated), so this is SQL-injection safe.
     */
    protected static function insertInto(string $table, array $fields): int
    {
        $columns = array_keys($fields);
        $placeholders = array_map(fn($c) => ":$c", $columns);
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );
        $stmt = static::db()->prepare($sql);
        $stmt->execute($fields);
        return (int) static::db()->lastInsertId();
    }

    protected static function updateTable(string $table, int $id, array $fields): bool
    {
        $assignments = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($fields)));
        $sql = "UPDATE $table SET $assignments WHERE id = :id";
        $fields['id'] = $id;
        $stmt = static::db()->prepare($sql);
        return $stmt->execute($fields);
    }
}
