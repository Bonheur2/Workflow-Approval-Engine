<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\Delegation;
use App\Models\RequestApproval;
use App\Models\WorkflowRequest;
use App\Services\WorkflowEngine;
use RuntimeException;

class ApprovalController
{
    /**
     * GET /api/approvals - requests currently awaiting action from the
     * authenticated user, including anything they can act on via an
     * active delegation from someone else.
     */
    public static function myQueue(Request $request): void
    {
        $userId = (int) $request->user['sub'];
        $direct = WorkflowRequest::pendingForApprover($userId);

        $delegatedFrom = [];
        // Find requests pending for anyone who has delegated to this user today.
        foreach (WorkflowRequest::all() as $req) {
            if ($req['status'] !== 'pending' || $req['current_step_order'] === null) {
                continue;
            }
            foreach (RequestApproval::forRequestStep((int) $req['id'], (int) $req['current_step_order']) as $approval) {
                if ($approval['status'] !== 'pending') {
                    continue;
                }
                $delegate = Delegation::activeDelegateFor((int) $approval['approver_id'], date('Y-m-d'));
                if ($delegate === $userId) {
                    $delegatedFrom[$req['id']] = $req;
                }
            }
        }

        $directIds = array_column($direct, 'id');
        $merged = $direct;
        foreach ($delegatedFrom as $id => $req) {
            if (!in_array($id, $directIds, true)) {
                $merged[] = $req;
            }
        }

        Response::success($merged);
    }

    /** POST /api/requests/{id}/approve */
    public static function approve(Request $request, array $params): void
    {
        self::runAction($request, $params, 'approve');
    }

    /** POST /api/requests/{id}/reject */
    public static function reject(Request $request, array $params): void
    {
        self::runAction($request, $params, 'reject');
    }

    /** POST /api/requests/{id}/return */
    public static function returnRequest(Request $request, array $params): void
    {
        self::runAction($request, $params, 'return');
    }

    private static function runAction(Request $request, array $params, string $action): void
    {
        $id = (int) $params['id'];
        $comments = $request->input('comments');
        $userId = (int) $request->user['sub'];

        try {
            $result = match ($action) {
                'approve' => WorkflowEngine::approve($id, $userId, $comments),
                'reject' => WorkflowEngine::reject($id, $userId, $comments),
                'return' => WorkflowEngine::returnForModification($id, $userId, $comments),
            };
            Response::success($result, "Request $action action recorded.");
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }
    }
}
