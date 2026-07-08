<?php

namespace App\Models;


class AuditLog extends BaseModel
{
    protected static string $table = 'audit_logs';

    public static function record(
        int $requestId,
        string $action,
        int $userId,
        ?string $previousStatus,
        ?string $newStatus,
        ?string $comments
    ): int {
        return static::insertInto('audit_logs', [
            'request_id' => $requestId,
            'action' => $action,
            'user_id' => $userId,
            'previous_status' => $previousStatus,
            'new_status' => $newStatus,
            'comments' => $comments,
        ]);
    }

    public static function forRequest(int $requestId): array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM audit_logs WHERE request_id = :rid ORDER BY created_at ASC, id ASC'
        );
        $stmt->execute(['rid' => $requestId]);
        return $stmt->fetchAll();
    }
}
