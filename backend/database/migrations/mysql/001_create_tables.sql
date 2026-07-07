-- Workflow & Approval Engine schema (MySQL dialect)
-- Run with: mysql -u root -p workflow_engine < 001_create_tables.sql

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'approver', 'requester') NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS workflows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    version INT UNSIGNED NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Steps are versioned implicitly: a workflow's steps are only ever edited
-- while no requests are "in flight" against that exact step set; in-flight
-- requests store a snapshot (requests.workflow_snapshot) so editing a
-- workflow never changes the behaviour of requests already in progress.
CREATE TABLE IF NOT EXISTS workflow_steps (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT UNSIGNED NOT NULL,
    step_order INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    approver_role ENUM('admin', 'approver', 'requester') NULL,
    approver_user_id INT UNSIGNED NULL,
    approval_type ENUM('single', 'all') NOT NULL DEFAULT 'single',
    conditions JSON NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_workflow_step (workflow_id, step_order),
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    workflow_id INT UNSIGNED NOT NULL,
    requester_id INT UNSIGNED NOT NULL,
    data JSON NOT NULL,
    workflow_snapshot JSON NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'returned') NOT NULL DEFAULT 'pending',
    current_step_order INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (workflow_id) REFERENCES workflows(id),
    FOREIGN KEY (requester_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS request_approvals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    step_order INT UNSIGNED NOT NULL,
    approver_id INT UNSIGNED NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'skipped') NOT NULL DEFAULT 'pending',
    acted_by INT UNSIGNED NULL,
    comments TEXT,
    acted_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (approver_id) REFERENCES users(id),
    FOREIGN KEY (acted_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audit trail: insert-only by convention (no UPDATE/DELETE statements are
-- ever issued against this table by the application layer). Consider
-- revoking UPDATE/DELETE grants on this table for the app DB user in prod.
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    previous_status VARCHAR(50),
    new_status VARCHAR(50),
    comments TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS delegations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delegator_id INT UNSIGNED NOT NULL,
    delegate_id INT UNSIGNED NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delegator_id) REFERENCES users(id),
    FOREIGN KEY (delegate_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    request_id INT UNSIGNED NULL,
    type VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (request_id) REFERENCES requests(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_requests_workflow ON requests(workflow_id);
CREATE INDEX idx_requests_requester ON requests(requester_id);
CREATE INDEX idx_approvals_request ON request_approvals(request_id);
CREATE INDEX idx_approvals_approver ON request_approvals(approver_id);
CREATE INDEX idx_audit_request ON audit_logs(request_id);
CREATE INDEX idx_notifications_user ON notifications(user_id);
CREATE INDEX idx_delegations_delegator ON delegations(delegator_id);

SET FOREIGN_KEY_CHECKS = 1;
