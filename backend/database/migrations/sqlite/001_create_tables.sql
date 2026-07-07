-- Workflow & Approval Engine schema (SQLite dialect)

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('admin', 'approver', 'requester')),
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS workflows (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    description TEXT,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    version INTEGER NOT NULL DEFAULT 1,
    created_by INTEGER NOT NULL REFERENCES users(id),
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Steps are versioned implicitly: a workflow's steps are only ever edited
-- while no requests are "in flight" against that exact step set; in-flight
-- requests store a snapshot (requests.workflow_snapshot) so editing a
-- workflow never changes the behaviour of requests already in progress.
CREATE TABLE IF NOT EXISTS workflow_steps (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workflow_id INTEGER NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
    step_order INTEGER NOT NULL,
    name TEXT NOT NULL,
    approver_role TEXT CHECK (approver_role IN ('admin', 'approver', 'requester') OR approver_role IS NULL),
    approver_user_id INTEGER REFERENCES users(id),
    approval_type TEXT NOT NULL DEFAULT 'single' CHECK (approval_type IN ('single', 'all')),
    conditions TEXT NOT NULL DEFAULT '[]', -- JSON array of {field,operator,value}
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE(workflow_id, step_order)
);

CREATE TABLE IF NOT EXISTS requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    workflow_id INTEGER NOT NULL REFERENCES workflows(id),
    requester_id INTEGER NOT NULL REFERENCES users(id),
    data TEXT NOT NULL DEFAULT '{}', -- JSON payload the conditions evaluate against
    workflow_snapshot TEXT NOT NULL, -- JSON snapshot of steps at submission time
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'returned')),
    current_step_order INTEGER,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS request_approvals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    step_order INTEGER NOT NULL,
    approver_id INTEGER NOT NULL REFERENCES users(id),
    status TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'skipped')),
    acted_by INTEGER REFERENCES users(id), -- actual actor, may differ from approver_id if delegated
    comments TEXT,
    acted_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

-- Audit trail: insert-only by convention (no UPDATE/DELETE statements are
-- ever issued against this table by the application layer).
CREATE TABLE IF NOT EXISTS audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    request_id INTEGER NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    action TEXT NOT NULL,
    user_id INTEGER NOT NULL REFERENCES users(id),
    previous_status TEXT,
    new_status TEXT,
    comments TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS delegations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    delegator_id INTEGER NOT NULL REFERENCES users(id),
    delegate_id INTEGER NOT NULL REFERENCES users(id),
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id),
    request_id INTEGER REFERENCES requests(id),
    type TEXT NOT NULL,
    message TEXT NOT NULL,
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_requests_workflow ON requests(workflow_id);
CREATE INDEX IF NOT EXISTS idx_requests_requester ON requests(requester_id);
CREATE INDEX IF NOT EXISTS idx_approvals_request ON request_approvals(request_id);
CREATE INDEX IF NOT EXISTS idx_approvals_approver ON request_approvals(approver_id);
CREATE INDEX IF NOT EXISTS idx_audit_request ON audit_logs(request_id);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_delegations_delegator ON delegations(delegator_id);
