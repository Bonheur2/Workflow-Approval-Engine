# Database Schema

Two equivalent migrations are provided:
- `database/migrations/sqlite/001_create_tables.sql` (default, zero-config)
- `database/migrations/mysql/001_create_tables.sql` (production-style alternative)

## Entity-relationship overview

```
users
 ├─< workflows.created_by
 ├─< workflow_steps.approver_user_id (optional, specific-approver steps)
 ├─< requests.requester_id
 ├─< request_approvals.approver_id / acted_by
 ├─< audit_logs.user_id
 ├─< delegations.delegator_id / delegate_id
 └─< notifications.user_id

workflows (1) ──< workflow_steps (many)
workflows (1) ──< requests (many)

requests (1) ──< request_approvals (many)
requests (1) ──< audit_logs (many)
requests (1) ──< notifications (many, optional)
```

## Table responsibilities

| Table              | Purpose |
|---------------------|---------|
| `users`             | Accounts + role (`admin`, `approver`, `requester`) + active flag. |
| `workflows`         | A named, versioned approval process (e.g. "Purchase Request"). `status` toggles whether it accepts new submissions. |
| `workflow_steps`    | The *current, live* definition of a workflow's steps: order, who approves (`approver_role` OR a specific `approver_user_id`), `approval_type` (`single`/`all`), and a JSON `conditions` array for conditional routing. |
| `requests`          | A submitted instance of a workflow. Stores the requester's `data` payload (the fields conditions evaluate against) **and** a `workflow_snapshot` - a frozen copy of the steps at submission time. |
| `request_approvals` | One row per approver assigned to a request at a given step. Tracks `status` (`pending`/`approved`/`rejected`/`skipped`), who actually acted (`acted_by`, which may differ from `approver_id` under delegation), and comments. |
| `audit_logs`        | Append-only history of every state transition. The application layer never issues `UPDATE`/`DELETE` against this table. |
| `delegations`       | Time-boxed delegation of one approver's responsibilities to another authorized user. |
| `notifications`     | A simple in-app inbox recording every submitted/approved/rejected/returned/awaiting-approval event. |

## Why a workflow "snapshot" on each request?

The challenge requires that *"a workflow should remain editable without
affecting requests that are already in progress."* Rather than trying to
version every edit as a new row (which gets complicated fast when steps
are added, removed, and reordered independently), each request stores a
JSON snapshot of the exact step list it was submitted against
(`requests.workflow_snapshot`). The engine always evaluates a request
against its own snapshot, never against the live `workflow_steps` table.
Editing a workflow (`PUT /api/workflows/{id}/steps`) is then a simple,
safe operation: it only ever affects *future* submissions.
