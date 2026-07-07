<?php

namespace App\Models;

class Workflow extends BaseModel
{
    protected static string $table = 'workflows';

    public static function create(string $name, ?string $description, int $createdBy): int
    {
        return static::insertInto('workflows', [
            'name' => $name,
            'description' => $description,
            'status' => 'active',
            'version' => 1,
            'created_by' => $createdBy,
        ]);
    }

    public static function update(int $id, array $fields): bool
    {
        $allowed = array_intersect_key($fields, array_flip(['name', 'description', 'status']));
        if (empty($allowed)) {
            return true;
        }
        // Editing a workflow bumps its version. In-flight requests are
        // unaffected because they hold a snapshot of the steps taken at
        // submission time (see requests.workflow_snapshot).
        $current = static::find($id);
        $allowed['version'] = ($current['version'] ?? 1) + 1;
        $allowed['updated_at'] = date('Y-m-d H:i:s');
        return static::updateTable('workflows', $id, $allowed);
    }

    public static function activeWorkflows(): array
    {
        return static::db()->query("SELECT * FROM workflows WHERE status = 'active'")->fetchAll();
    }

    /** Steps ordered by step_order, for the current (live) definition of the workflow. */
    public static function stepsFor(int $workflowId): array
    {
        $stmt = static::db()->prepare(
            'SELECT * FROM workflow_steps WHERE workflow_id = :id ORDER BY step_order ASC'
        );
        $stmt->execute(['id' => $workflowId]);
        return $stmt->fetchAll();
    }
}
