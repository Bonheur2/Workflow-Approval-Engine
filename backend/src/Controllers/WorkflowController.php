<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Workflow;
use App\Models\WorkflowStep;

class WorkflowController
{
    /**
     * GET /api/workflows
     * Admins see every workflow (including inactive drafts); everyone
     * else only sees workflows currently open for submission.
     */
    public static function index(Request $request): void
    {
        $isAdmin = ($request->user['role'] ?? null) === 'admin';
        $workflows = $isAdmin ? Workflow::all() : Workflow::activeWorkflows();
        Response::success($workflows);
    }

    /** GET /api/workflows/{id} - includes the current step definitions */
    public static function show(Request $request, array $params): void
    {
        $workflow = Workflow::find((int) $params['id']);
        if (!$workflow) {
            Response::error('Workflow not found.', 404);
            return;
        }
        $workflow['steps'] = array_map([self::class, 'decodeStep'], Workflow::stepsFor((int) $params['id']));
        Response::success($workflow);
    }

    /**
     * POST /api/workflows - admin only.
     * Body: { name, description, steps: [{ step_order, name, approver_role,
     *          approver_user_id, approval_type, conditions }] }
     */
    public static function store(Request $request): void
    {
        $data = $request->all();
        $v = Validator::make($data, [
            'name' => 'required|string|max:255',
            'description' => 'string',
            'steps' => 'required|array',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
            return;
        }

        $stepErrors = self::validateSteps($data['steps']);
        if ($stepErrors) {
            Response::error('Validation failed.', 422, ['steps' => $stepErrors]);
            return;
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $workflowId = Workflow::create($data['name'], $data['description'] ?? null, (int) $request->user['sub']);
            self::persistSteps($workflowId, $data['steps']);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $workflow = Workflow::find($workflowId);
        $workflow['steps'] = array_map([self::class, 'decodeStep'], Workflow::stepsFor($workflowId));
        Response::success($workflow, 'Workflow created.', 201);
    }

    /** PUT /api/workflows/{id} - admin only. Updates name/description/status. */
    public static function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!Workflow::find($id)) {
            Response::error('Workflow not found.', 404);
            return;
        }
        $data = $request->all();
        if (isset($data['status'])) {
            $v = Validator::make($data, ['status' => 'in:active,inactive']);
            if ($v->fails()) {
                Response::error('Validation failed.', 422, $v->errors());
                return;
            }
        }
        Workflow::update($id, $data);
        Response::success(Workflow::find($id), 'Workflow updated.');
    }

    /**
     * PUT /api/workflows/{id}/steps - admin only.
     * Replaces the full step list. Requests already in progress are
     * unaffected because they hold their own snapshot taken at
     * submission time.
     */
    public static function replaceSteps(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!Workflow::find($id)) {
            Response::error('Workflow not found.', 404);
            return;
        }
        $data = $request->all();
        $v = Validator::make($data, ['steps' => 'required|array']);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
            return;
        }
        $stepErrors = self::validateSteps($data['steps']);
        if ($stepErrors) {
            Response::error('Validation failed.', 422, ['steps' => $stepErrors]);
            return;
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            WorkflowStep::deleteAllForWorkflow($id);
            self::persistSteps($id, $data['steps']);
            Workflow::update($id, []); // bump version, touch updated_at
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        $workflow = Workflow::find($id);
        $workflow['steps'] = array_map([self::class, 'decodeStep'], Workflow::stepsFor($id));
        Response::success($workflow, 'Workflow steps updated.');
    }

    private static function validateSteps(array $steps): array
    {
        $errors = [];
        $orders = [];
        foreach ($steps as $i => $step) {
            if (empty($step['name'])) {
                $errors[] = "Step at index $i is missing a name.";
            }
            if (!isset($step['step_order']) || !is_numeric($step['step_order'])) {
                $errors[] = "Step at index $i is missing a numeric step_order.";
            } else {
                $orders[] = (int) $step['step_order'];
            }
            if (empty($step['approver_role']) && empty($step['approver_user_id'])) {
                $errors[] = "Step at index $i must define either approver_role or approver_user_id.";
            }
            $type = $step['approval_type'] ?? 'single';
            if (!in_array($type, WorkflowStep::APPROVAL_TYPES, true)) {
                $errors[] = "Step at index $i has an invalid approval_type.";
            }
            if (isset($step['conditions']) && !is_array($step['conditions'])) {
                $errors[] = "Step at index $i conditions must be an array.";
            }
        }
        if (count($orders) !== count(array_unique($orders))) {
            $errors[] = 'step_order values must be unique within a workflow.';
        }
        return $errors;
    }

    private static function persistSteps(int $workflowId, array $steps): void
    {
        foreach ($steps as $step) {
            WorkflowStep::create(
                $workflowId,
                (int) $step['step_order'],
                $step['name'],
                $step['approver_role'] ?? null,
                isset($step['approver_user_id']) ? (int) $step['approver_user_id'] : null,
                $step['approval_type'] ?? 'single',
                $step['conditions'] ?? []
            );
        }
    }

    private static function decodeStep(array $step): array
    {
        $step['conditions'] = json_decode($step['conditions'], true);
        return $step;
    }
}
