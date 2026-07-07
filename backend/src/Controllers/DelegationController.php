<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\Delegation;
use App\Models\User;

class DelegationController
{
    /** POST /api/delegations - an approver delegates to another authorized user for a period */
    public static function store(Request $request): void
    {
        $data = $request->all();
        $v = Validator::make($data, [
            'delegate_id' => 'required|integer',
            'start_date' => 'required|string',
            'end_date' => 'required|string',
        ]);
        if ($v->fails()) {
            Response::error('Validation failed.', 422, $v->errors());
            return;
        }

        $delegatorId = (int) $request->user['sub'];
        $delegateId = (int) $data['delegate_id'];

        if ($delegatorId === $delegateId) {
            Response::error('You cannot delegate to yourself.', 422);
            return;
        }
        $delegate = User::find($delegateId);
        if (!$delegate || !$delegate['is_active']) {
            Response::error('Delegate user not found or inactive.', 422);
            return;
        }
        if (!in_array($delegate['role'], ['approver', 'admin'], true)) {
            Response::error('Delegate must be an authorized approver or administrator.', 422);
            return;
        }
        if (strtotime($data['end_date']) < strtotime($data['start_date'])) {
            Response::error('end_date must be on or after start_date.', 422);
            return;
        }

        $id = Delegation::create($delegatorId, $delegateId, $data['start_date'], $data['end_date']);
        Response::success(Delegation::find($id), 'Delegation created.', 201);
    }

    /** GET /api/delegations - delegations created by the authenticated user */
    public static function index(Request $request): void
    {
        Response::success(Delegation::forDelegator((int) $request->user['sub']));
    }

    /** DELETE /api/delegations/{id} - revoke (only the delegator may revoke their own delegation) */
    public static function revoke(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $delegation = Delegation::find($id);
        if (!$delegation) {
            Response::error('Delegation not found.', 404);
            return;
        }
        if ((int) $delegation['delegator_id'] !== (int) $request->user['sub']) {
            Response::error('You may only revoke your own delegations.', 403);
            return;
        }
        Delegation::revoke($id);
        Response::success(null, 'Delegation revoked.');
    }
}
