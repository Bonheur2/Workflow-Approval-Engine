<?php

namespace App\Models;

class User extends BaseModel
{
    protected static string $table = 'users';

    public const ROLES = ['admin', 'approver', 'requester'];

    public static function findByEmail(string $email): ?array
    {
        $stmt = static::db()->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function create(string $name, string $email, string $password, string $role): int
    {
        return static::insertInto('users', [
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'is_active' => 1,
        ]);
    }

    public static function findAllByRole(string $role): array
    {
        $stmt = static::db()->prepare('SELECT * FROM users WHERE role = :role AND is_active = 1');
        $stmt->execute(['role' => $role]);
        return $stmt->fetchAll();
    }

    /** Bulk id => name lookup, e.g. for labeling approvals/audit trail rows without exposing the admin-only /users endpoint. */
    public static function namesByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = static::db()->prepare("SELECT id, name FROM users WHERE id IN ($placeholders)");
        $stmt->execute($ids);
        $names = [];
        foreach ($stmt->fetchAll() as $row) {
            $names[(int) $row['id']] = $row['name'];
        }
        return $names;
    }

    public static function setActive(int $id, bool $active): bool
    {
        return static::updateTable('users', $id, ['is_active' => $active ? 1 : 0]);
    }

    public static function updateRole(int $id, string $role): bool
    {
        return static::updateTable('users', $id, ['role' => $role]);
    }

    /** Strip sensitive fields before sending a user record back over the API. */
    public static function sanitize(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }
}
