
CREATE TABLE IF NOT EXISTS users (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) NOT NULL CHECK (role IN ('admin', 'approver', 'requester')),
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS workflows (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive')),
    version INTEGER NOT NULL DEFAULT 1,
    created_by INTEGER NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Steps are versioned implicitly: a workflow's steps are only ever edited
-- while no requests are "in flight" against that exact step set; in-flight
-- requests store a snapshot (requests.workflow_snapshot) so editing a
-- workflow never changes the behaviour of requests already in progress.
CREATE TABLE IF NOT EXISTS workflow_steps (
    id SERIAL PRIMARY KEY,
    workflow_id INTEGER NOT NULL REFERENCES workflows(id) ON DELETE CASCADE,
    step_order INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    approver_role VARCHAR(20) CHECK (approver_role IN ('admin', 'approver', 'requester') OR approver_role IS NULL),
    approver_user_id INTEGER REFERENCES users(id),
    approval_type VARCHAR(20) NOT NULL DEFAULT 'single' CHECK (approval_type IN ('single', 'all')),
    conditions TEXT NOT NULL DEFAULT '[]',
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    UNIQUE (workflow_id, step_order)
);

CREATE TABLE IF NOT EXISTS requests (
    id SERIAL PRIMARY KEY,
    workflow_id INTEGER NOT NULL REFERENCES workflows(id),
    requester_id INTEGER NOT NULL REFERENCES users(id),
    data TEXT NOT NULL DEFAULT '{}',
    workflow_snapshot TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'returned')),
    current_step_order INTEGER,
    created_at TIMESTAMP NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS request_approvals (
    id SERIAL PRIMARY KEY,
    request_id INTEGER NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    step_order INTEGER NOT NULL,
    approver_id INTEGER NOT NULL REFERENCES users(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'skipped')),
    acted_by INTEGER REFERENCES users(id),
    comments TEXT,
    acted_at TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- Audit trail: insert-only by convention (no UPDATE/DELETE statements are
-- ever issued against this table by the application layer).
CREATE TABLE IF NOT EXISTS audit_logs (
    id SERIAL PRIMARY KEY,
    request_id INTEGER NOT NULL REFERENCES requests(id) ON DELETE CASCADE,
    action VARCHAR(100) NOT NULL,
    user_id INTEGER NOT NULL REFERENCES users(id),
    previous_status VARCHAR(50),
    new_status VARCHAR(50),
    comments TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS delegations (
    id SERIAL PRIMARY KEY,
    delegator_id INTEGER NOT NULL REFERENCES users(id),
    delegate_id INTEGER NOT NULL REFERENCES users(id),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS notifications (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id),
    request_id INTEGER REFERENCES requests(id),
    type VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_requests_workflow ON requests(workflow_id);
CREATE INDEX IF NOT EXISTS idx_requests_requester ON requests(requester_id);
CREATE INDEX IF NOT EXISTS idx_approvals_request ON request_approvals(request_id);
CREATE INDEX IF NOT EXISTS idx_approvals_approver ON request_approvals(approver_id);
CREATE INDEX IF NOT EXISTS idx_audit_request ON audit_logs(request_id);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id);
CREATE INDEX IF NOT EXISTS idx_delegations_delegator ON delegations(delegator_id);
