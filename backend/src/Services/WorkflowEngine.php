<?php

namespace App\Services;

use App\Core\Database;
use App\Models\Delegation;
use App\Models\RequestApproval;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRequest;
use RuntimeException;

/**
 * The dynamic Workflow & Approval engine.
 *
 * This is the one place that understands how a request moves through a
 * workflow. It never hardcodes a workflow's shape - every decision (which
 * step is next, who must approve it, whether the step is even reachable)
 * is derived at runtime from the workflow's step definitions and the
 * request's data payload.
 *
 * Key design decisions (see README "Assumptions" for the full list):
 *  - A request stores an immutable snapshot of the workflow's steps at
 *    submission time, so administrators can freely edit a workflow
 *    without changing the behaviour of requests already in progress.
 *  - Steps whose conditions evaluate to false are skipped entirely -
 *    they never generate an approval row.
 *  - approval_type = "all" requires every assigned approver to approve;
 *    approval_type = "single" requires only the first response, after
 *    which the other assigned approvers' pending rows are marked "skipped".
 *  - A rejection at any step immediately rejects the whole request.
 *  - "Return for modification" sends the request back to the requester;
 *    resubmitting restarts evaluation from the first reachable step.
 */
class WorkflowEngine
{
    /**
     * Submit a new request against a workflow and activate its first
     * reachable step.
     */
    public static function submit(int $workflowId, int $requesterId, array $data): array
    {
        $workflow = Workflow::find($workflowId);
        if (!$workflow) {
            throw new RuntimeException('Workflow not found.');
        }
        if ($workflow['status'] !== 'active') {
            throw new RuntimeException('This workflow is not active and cannot accept new requests.');
        }

        $steps = Workflow::stepsFor($workflowId);
        if (empty($steps)) {
            throw new RuntimeException('This workflow has no approval steps configured.');
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            $requestId = WorkflowRequest::create($workflowId, $requesterId, $data, $steps);

            AuditService::log($requestId, 'submitted', $requesterId, null, 'pending', 'Request submitted.');
            NotificationService::requestSubmitted($requesterId, $requestId);

            self::activateNextStep($requestId, $steps, $data, 0, $requesterId);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return WorkflowRequest::find($requestId);
    }

    /**
     * Approve a request on behalf of $actorUserId (who may be acting as a
     * delegate for the assigned approver).
     */
    public static function approve(int $requestId, int $actorUserId, ?string $comments): array
    {
        return self::act($requestId, $actorUserId, 'approve', $comments);
    }

    public static function reject(int $requestId, int $actorUserId, ?string $comments): array
    {
        return self::act($requestId, $actorUserId, 'reject', $comments);
    }

    public static function returnForModification(int $requestId, int $actorUserId, ?string $comments): array
    {
        return self::act($requestId, $actorUserId, 'return', $comments);
    }

    /**
     * Requester edits and resubmits a "returned" request. Evaluation
     * restarts from the first reachable step against the updated data.
     */
    public static function resubmit(int $requestId, int $requesterId, array $newData): array
    {
        $request = WorkflowRequest::find($requestId);
        if (!$request) {
            throw new RuntimeException('Request not found.');
        }
        if ((int) $request['requester_id'] !== $requesterId) {
            throw new RuntimeException('Only the original requester may resubmit this request.');
        }
        if ($request['status'] !== 'returned') {
            throw new RuntimeException('Only returned requests can be resubmitted.');
        }

        $steps = json_decode($request['workflow_snapshot'], true);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            WorkflowRequest::updateData($requestId, $newData);
            WorkflowRequest::updateStatus($requestId, 'pending', null);
            AuditService::log($requestId, 'resubmitted', $requesterId, 'returned', 'pending', 'Request resubmitted after modification.');

            self::activateNextStep($requestId, $steps, $newData, 0, $requesterId);

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return WorkflowRequest::find($requestId);
    }

    // ------------------------------------------------------------------
    // Internal mechanics
    // ------------------------------------------------------------------

    private static function act(int $requestId, int $actorUserId, string $action, ?string $comments): array
    {
        $request = WorkflowRequest::find($requestId);
        if (!$request) {
            throw new RuntimeException('Request not found.');
        }
        if ($request['status'] !== 'pending') {
            throw new RuntimeException('This request is not currently pending approval.');
        }
        $stepOrder = $request['current_step_order'];
        if ($stepOrder === null) {
            throw new RuntimeException('This request has no active approval step.');
        }

        $approvalRow = self::resolveActionableRow($requestId, (int) $stepOrder, $actorUserId);
        if (!$approvalRow) {
            throw new RuntimeException('You are not authorized to act on this request at its current step.');
        }

        $steps = json_decode($request['workflow_snapshot'], true);
        $data = json_decode($request['data'], true);
        $currentStepDef = self::findStepDef($steps, (int) $stepOrder);

        $db = Database::connection();
        $db->beginTransaction();
        try {
            if ($action === 'reject') {
                RequestApproval::markActed($approvalRow['id'], 'rejected', $actorUserId, $comments);
                RequestApproval::skipRemaining($requestId, (int) $stepOrder);
                WorkflowRequest::updateStatus($requestId, 'rejected', (int) $stepOrder);
                AuditService::log($requestId, 'rejected', $actorUserId, 'pending', 'rejected', $comments);
                NotificationService::requestRejected((int) $request['requester_id'], $requestId, $comments);
            } elseif ($action === 'return') {
                RequestApproval::markActed($approvalRow['id'], 'rejected', $actorUserId, $comments);
                RequestApproval::skipRemaining($requestId, (int) $stepOrder);
                WorkflowRequest::updateStatus($requestId, 'returned', (int) $stepOrder);
                AuditService::log($requestId, 'returned', $actorUserId, 'pending', 'returned', $comments);
                NotificationService::requestReturned((int) $request['requester_id'], $requestId, $comments);
            } else { // approve
                RequestApproval::markActed($approvalRow['id'], 'approved', $actorUserId, $comments);
                AuditService::log($requestId, 'approved_step', $actorUserId, 'pending', 'pending', $comments);

                if (self::stepIsComplete($requestId, (int) $stepOrder, $currentStepDef)) {
                    self::activateNextStep($requestId, $steps, $data, (int) $stepOrder, (int) $request['requester_id']);
                }
            }
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        return WorkflowRequest::find($requestId);
    }

    /**
     * Finds the request_approvals row the given user is entitled to act
     * on for the given step - either because they are the assigned
     * approver, or because they hold an active delegation from the
     * assigned approver covering today's date.
     */
    private static function resolveActionableRow(int $requestId, int $stepOrder, int $actorUserId): ?array
    {
        $rows = RequestApproval::forRequestStep($requestId, $stepOrder);
        $today = date('Y-m-d');

        foreach ($rows as $row) {
            if ($row['status'] !== 'pending') {
                continue;
            }
            if ((int) $row['approver_id'] === $actorUserId) {
                return $row;
            }
            $delegate = Delegation::activeDelegateFor((int) $row['approver_id'], $today);
            if ($delegate === $actorUserId) {
                return $row;
            }
        }
        return null;
    }

    private static function stepIsComplete(int $requestId, int $stepOrder, array $stepDef): bool
    {
        $rows = RequestApproval::forRequestStep($requestId, $stepOrder);
        if ($stepDef['approval_type'] === 'all') {
            foreach ($rows as $row) {
                if ($row['status'] === 'pending') {
                    return false;
                }
            }
            return true;
        }
        // "single": complete as soon as at least one approval is recorded.
        foreach ($rows as $row) {
            if ($row['status'] === 'approved') {
                RequestApproval::skipRemaining($requestId, $stepOrder);
                return true;
            }
        }
        return false;
    }

    private static function findStepDef(array $steps, int $stepOrder): array
    {
        foreach ($steps as $step) {
            if ((int) $step['step_order'] === $stepOrder) {
                return $step;
            }
        }
        throw new RuntimeException("Step $stepOrder not found in workflow snapshot.");
    }

    /**
     * Walks forward through the snapshot from (afterStepOrder, ∞), looking
     * for the first step whose conditions evaluate to true against $data.
     * Activates it (creates approval rows + notifications), or - if no
     * further step is reachable - marks the request approved.
     */
    private static function activateNextStep(
        int $requestId,
        array $steps,
        array $data,
        int $afterStepOrder,
        int $requesterId
    ): void {
        usort($steps, fn($a, $b) => $a['step_order'] <=> $b['step_order']);

        foreach ($steps as $step) {
            if ((int) $step['step_order'] <= $afterStepOrder) {
                continue;
            }
            $conditions = is_string($step['conditions']) ? json_decode($step['conditions'], true) : $step['conditions'];
            $conditions = $conditions ?? [];

            if (!ConditionEvaluator::evaluate($conditions, $data)) {
                continue; // step not reachable for this request, skip it
            }

            $approverIds = self::resolveApprovers($step, $requesterId);
            if (empty($approverIds)) {
                // No eligible approver found for this step (or the only
                // match was the requester themselves, excluded below to
                // prevent self-approval); skip forward rather than
                // deadlocking the request.
                continue;
            }

            foreach ($approverIds as $approverId) {
                RequestApproval::create($requestId, (int) $step['step_order'], $approverId);
                NotificationService::awaitingApproval($approverId, $requestId);
            }

            WorkflowRequest::updateStatus($requestId, 'pending', (int) $step['step_order']);
            AuditService::log(
                $requestId,
                'step_activated',
                $requesterId,
                null,
                'pending',
                "Step '{$step['name']}' (order {$step['step_order']}) activated."
            );
            return;
        }

        // No further reachable step: the request has cleared every
        // applicable stage, so the final outcome is "approved".
        WorkflowRequest::updateStatus($requestId, 'approved', null);
        AuditService::log($requestId, 'approved', $requesterId, 'pending', 'approved', 'All required approvals obtained.');
        NotificationService::requestApproved($requesterId, $requestId);
    }

    /**
     * Resolves the step's assigned approver(s), excluding the requester
     * themselves - a requester who also happens to hold the approver role
     * (or is specifically named on the step) must never be able to approve
     * their own request.
     */
    private static function resolveApprovers(array $step, int $requesterId): array
    {
        if (!empty($step['approver_user_id'])) {
            $userId = (int) $step['approver_user_id'];
            return $userId === $requesterId ? [] : [$userId];
        }
        if (!empty($step['approver_role'])) {
            $users = User::findAllByRole($step['approver_role']);
            $ids = array_map(fn($u) => (int) $u['id'], $users);
            return array_values(array_filter($ids, fn($id) => $id !== $requesterId));
        }
        return [];
    }
}
