<?php

namespace App\Models;

class WorkflowStep extends BaseModel
{
    protected static string $table = 'workflow_steps';

    public const APPROVAL_TYPES = ['single', 'all'];

    /**
     * @param array $conditions Array of {field, operator, value} triples.
     */
    public static function create(
        int $workflowId,
        int $stepOrder,
        string $name,
        ?string $approverRole,
        ?int $approverUserId,
        string $approvalType,
        array $conditions
    ): int {
        return static::insertInto('workflow_steps', [
            'workflow_id' => $workflowId,
            'step_order' => $stepOrder,
            'name' => $name,
            'approver_role' => $approverRole,
            'approver_user_id' => $approverUserId,
            'approval_type' => $approvalType,
            'conditions' => json_encode($conditions),
        ]);
    }

    public static function deleteAllForWorkflow(int $workflowId): void
    {
        $stmt = static::db()->prepare('DELETE FROM workflow_steps WHERE workflow_id = :id');
        $stmt->execute(['id' => $workflowId]);
    }
}
