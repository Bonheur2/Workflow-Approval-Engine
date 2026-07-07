<?php

namespace App\Models;

class WorkflowRequest extends BaseModel
{
    protected static string $table = 'requests';

    public const STATUSES = ['pending', 'approved', 'rejected', 'returned'];

    public static function create(int $workflowId, int $requesterId, array $data, array $snapshot): int
    {
        return static::insertInto('requests', [
            'workflow_id' => $workflowId,
            'requester_id' => $requesterId,
            'data' => json_encode($data),
            'workflow_snapshot' => json_encode($snapshot),
            'status' => 'pending',
            'current_step_order' => null,
        ]);
    }

    public static function updateStatus(int $id, string $status, ?int $currentStepOrder): bool
    {
        return static::updateTable('requests', $id, [
            'status' => $status,
            'current_step_order' => $currentStepOrder,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function updateData(int $id, array $data): bool
    {
        return static::updateTable('requests', $id, [
            'data' => json_encode($data),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function forRequester(int $requesterId): array
    {
        $stmt = static::db()->prepare('SELECT * FROM requests WHERE requester_id = :id ORDER BY created_at DESC');
        $stmt->execute(['id' => $requesterId]);
        return $stmt->fetchAll();
    }

    /** Requests currently awaiting action from a given approver (accounting for delegation is handled in the engine). */
    public static function pendingForApprover(int $approverId): array
    {
        $stmt = static::db()->prepare(
            'SELECT DISTINCT r.* FROM requests r
             JOIN request_approvals ra ON ra.request_id = r.id
             WHERE ra.approver_id = :approver_id AND ra.status = "pending" AND r.status = "pending"
             ORDER BY r.created_at ASC'
        );
        $stmt->execute(['approver_id' => $approverId]);
        return $stmt->fetchAll();
    }
}
