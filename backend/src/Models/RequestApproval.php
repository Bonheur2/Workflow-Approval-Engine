<?php

namespace App\Models;

class RequestApproval extends BaseModel
{
    protected static string $table = 'request_approvals';

    public static function create(int $requestId, int $stepOrder, int $approverId): int
    {
        return static::insertInto('request_approvals', [
            'request_id' => $requestId,
            'step_order' => $stepOrder,
            'approver_id' => $approverId,
            'status' => 'pending',
        ]);
    }

    public static function forRequestStep(int $requestId, int $stepOrder): array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM request_approvals WHERE request_id = :rid AND step_order = :step'
        );
        $stmt->execute(['rid' => $requestId, 'step' => $stepOrder]);
        return $stmt->fetchAll();
    }

    public static function forRequest(int $requestId): array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM request_approvals WHERE request_id = :rid ORDER BY step_order ASC, id ASC'
        );
        $stmt->execute(['rid' => $requestId]);
        return $stmt->fetchAll();
    }

    public static function findPendingForApprover(int $requestId, int $stepOrder, int $approverId): ?array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM request_approvals
             WHERE request_id = :rid AND step_order = :step AND approver_id = :approver AND status = \'pending\''
        );
        $stmt->execute(['rid' => $requestId, 'step' => $stepOrder, 'approver' => $approverId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function markActed(int $id, string $status, int $actedBy, ?string $comments): bool
    {
        return static::updateTable('request_approvals', $id, [
            'status' => $status,
            'acted_by' => $actedBy,
            'comments' => $comments,
            'acted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function skipRemaining(int $requestId, int $stepOrder): void
    {
        $stmt = static::db()->prepare(
            'UPDATE request_approvals SET status = \'skipped\'
             WHERE request_id = :rid AND step_order = :step AND status = \'pending\''
        );
        $stmt->execute(['rid' => $requestId, 'step' => $stepOrder]);
    }
}
