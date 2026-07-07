<?php

namespace App\Models;

class Notification extends BaseModel
{
    protected static string $table = 'notifications';

    public static function create(int $userId, ?int $requestId, string $type, string $message): int
    {
        return static::insertInto('notifications', [
            'user_id' => $userId,
            'request_id' => $requestId,
            'type' => $type,
            'message' => $message,
            'is_read' => 0,
        ]);
    }

    public static function forUser(int $userId, bool $unreadOnly = false): array
    {
        $sql = 'SELECT * FROM notifications WHERE user_id = :uid';
        if ($unreadOnly) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= ' ORDER BY created_at DESC';
        $stmt = static::db()->prepare($sql);
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function markRead(int $id): bool
    {
        return static::updateTable('notifications', $id, ['is_read' => 1]);
    }
}
