<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\AuditLog;
use App\Models\RequestApproval;
use App\Models\WorkflowRequest;
use App\Services\WorkflowEngine;
use RuntimeException;

class RequestController
{
    /** POST /api/requests - any authenticated user may submit a request */
    public static function store(Request $request): void
    {
        $data = $request->all();
        $v = Validator::make($data, [
            'workflow_id' => 'required|integer',
            'data' => 'array',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
            return;
        }

        try {
            $result = WorkflowEngine::submit(
                (int) $data['workflow_id'],
                (int) $request->user['sub'],
                $data['data'] ?? []
            );
            Response::success($result, 'Request submitted.', 201);
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }
    }

    /**
     * GET /api/requests
     * - admins: every request
     * - everyone else: their own submitted requests
     * (use GET /api/approvals for the "awaiting my action" queue)
     */
    public static function index(Request $request): void
    {
        $isAdmin = ($request->user['role'] ?? null) === 'admin';
        $requests = $isAdmin
            ? WorkflowRequest::all()
            : WorkflowRequest::forRequester((int) $request->user['sub']);
        Response::success($requests);
    }

    /** GET /api/requests/{id} - includes approval + audit history */
    public static function show(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $req = WorkflowRequest::find($id);
        if (!$req) {
            Response::error('Request not found.', 404);
            return;
        }

        $isAdmin = ($request->user['role'] ?? null) === 'admin';
        $isOwner = (int) $req['requester_id'] === (int) $request->user['sub'];
        $isInvolvedApprover = self::isInvolvedApprover($id, (int) $request->user['sub']);

        if (!$isAdmin && !$isOwner && !$isInvolvedApprover) {
            Response::error('You do not have permission to view this request.', 403);
            return;
        }

        $req['data'] = json_decode($req['data'], true);
        $req['workflow_snapshot'] = json_decode($req['workflow_snapshot'], true);
        $req['approvals'] = RequestApproval::forRequest($id);
        $req['audit_trail'] = AuditLog::forRequest($id);

        Response::success($req);
    }

    /** POST /api/requests/{id}/resubmit - requester edits + resubmits a "returned" request */
    public static function resubmit(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $data = $request->all();
        $v = Validator::make($data, ['data' => 'required|array']);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
            return;
        }

        try {
            $result = WorkflowEngine::resubmit($id, (int) $request->user['sub'], $data['data']);
            Response::success($result, 'Request resubmitted.');
        } catch (RuntimeException $e) {
            Response::error($e->getMessage(), 422);
        }
    }

    private static function isInvolvedApprover(int $requestId, int $userId): bool
    {
        foreach (RequestApproval::forRequest($requestId) as $approval) {
            if ((int) $approval['approver_id'] === $userId) {
                return true;
            }
        }
        return false;
    }
}
