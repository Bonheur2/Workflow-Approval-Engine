<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AuditLog;
use App\Models\RequestApproval;
use App\Models\WorkflowRequest;

class AuditController
{
    /** GET /api/requests/{id}/history - full, read-only audit trail for a request */
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
        $isInvolvedApprover = false;
        foreach (RequestApproval::forRequest($id) as $a) {
            if ((int) $a['approver_id'] === (int) $request->user['sub']) {
                $isInvolvedApprover = true;
                break;
            }
        }

        if (!$isAdmin && !$isOwner && !$isInvolvedApprover) {
            Response::error('You do not have permission to view this history.', 403);
            return;
        }

        Response::success(AuditLog::forRequest($id));
    }
}
