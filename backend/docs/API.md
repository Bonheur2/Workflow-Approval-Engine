# API Documentation

Base URL (local dev): `http://localhost:8000/api`

All endpoints (except `/auth/register` and `/auth/login`) require:
```
Authorization: Bearer <token>
```
obtained from `POST /auth/login`.

All responses use the envelope:
```json
{ "success": true|false, "message": "...", "data": {...} | "errors": {...} }
```

| Status | Meaning |
|---|---|
| 200 | OK |
| 201 | Created |
| 401 | Missing/invalid/expired token |
| 403 | Authenticated, but role/ownership doesn't permit this action |
| 404 | Resource not found |
| 422 | Validation failed / business rule violation (e.g. rejecting an already-closed request) |
| 500 | Unexpected server error (logged to `storage/logs/app.log`) |

---

## Authentication

### `POST /auth/register`
Public self-registration. Always creates a `requester` account (nobody can
self-elevate through this endpoint).
```json
{ "name": "Dan", "email": "dan@example.com", "password": "SecurePass123" }
```

### `POST /auth/login`
```json
{ "email": "dan@example.com", "password": "SecurePass123" }
```
Returns a JWT (`data.token`), its type/TTL, and the user record.

### `GET /auth/me`
Returns the authenticated user's profile.

---

## User management *(admin only)*

| Method | Path | Description |
|---|---|---|
| GET | `/users` | List all users |
| GET | `/users/{id}` | Show one user |
| POST | `/users` | Create a user with any role directly |
| PATCH | `/users/{id}/role` | Change a user's role |
| PATCH | `/users/{id}/status` | Activate/deactivate (`{ "is_active": false }`) |

---

## Workflow management

| Method | Path | Role | Description |
|---|---|---|---|
| GET | `/workflows` | any | Admins see all workflows; others see only `active` ones |
| GET | `/workflows/{id}` | any | Includes the current step definitions |
| POST | `/workflows` | admin | Create a workflow + its steps (see below) |
| PUT | `/workflows/{id}` | admin | Update `name`/`description`/`status` |
| PUT | `/workflows/{id}/steps` | admin | Replace the full step list (in-flight requests keep their own snapshot - unaffected) |

### Creating a workflow
```json
POST /workflows
{
  "name": "Purchase Request",
  "description": "Approval flow for purchases",
  "steps": [
    { "step_order": 1, "name": "Finance Review", "approver_role": "approver", "approval_type": "single", "conditions": [] },
    { "step_order": 2, "name": "Legal Review", "approver_role": "approver", "approval_type": "single",
      "conditions": [ { "field": "amount", "operator": ">", "value": 10000 } ] },
    { "step_order": 3, "name": "Executive Approval", "approver_role": "approver", "approval_type": "all",
      "conditions": [ { "field": "amount", "operator": ">", "value": 50000 } ] }
  ]
}
```
- `approver_role` **or** `approver_user_id` must be set (role = any active user
  with that role is eligible; user_id = one specific person).
- `approval_type`: `"single"` (first response wins) or `"all"` (every
  assigned approver must approve - this is the parallel-approval case).
- `conditions`: array of `{ field, operator, value }`, AND-combined.
  Supported operators: `=`, `!=`, `>`, `>=`, `<`, `<=`, `contains`, `in`.
  An empty array means the step always applies.

---

## Requests

| Method | Path | Description |
|---|---|---|
| POST | `/requests` | Submit a request: `{ "workflow_id": 1, "data": { "amount": 12000, ... } }` |
| GET | `/requests` | Admin: all requests. Others: their own submitted requests. |
| GET | `/requests/{id}` | Full detail incl. `approvals` and `audit_trail` |
| POST | `/requests/{id}/resubmit` | Requester edits + resubmits a `returned` request: `{ "data": {...} }` |
| GET | `/requests/{id}/history` | Read-only audit trail only |

`data` is a free-form JSON object - whatever fields the workflow's step
conditions reference (`amount`, `department`, `employee_level`, `country`, ...).

---

## Approval actions

| Method | Path | Role | Description |
|---|---|---|---|
| GET | `/approvals` | approver/admin | Requests currently awaiting *your* action (direct or via delegation) |
| POST | `/requests/{id}/approve` | approver/admin | `{ "comments": "optional" }` |
| POST | `/requests/{id}/reject` | approver/admin | `{ "comments": "optional" }` - rejects the whole request |
| POST | `/requests/{id}/return` | approver/admin | `{ "comments": "optional" }` - sends back to requester for edits |

---

## Delegation

| Method | Path | Description |
|---|---|---|
| POST | `/delegations` | `{ "delegate_id": 3, "start_date": "2026-07-01", "end_date": "2026-07-10" }` |
| GET | `/delegations` | Delegations you've created |
| DELETE | `/delegations/{id}` | Revoke your own delegation |

While a delegation is active, the delegate can approve/reject/return on
behalf of the delegator; `request_approvals.acted_by` records who
*actually* acted, so delegated approvals stay fully traceable even though
`approver_id` still shows the original assignee.

---

## Notifications

| Method | Path | Description |
|---|---|---|
| GET | `/notifications?unread=1` | Your notifications (optionally unread-only) |
| PATCH | `/notifications/{id}/read` | Mark one as read |

---

## Health check

`GET /health` - no auth required. Returns `{ "status": "ok", "time": "..." }`.
