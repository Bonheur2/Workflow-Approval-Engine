<?php

/**
 * Seeds a minimal, demo-ready dataset:
 *  - 1 admin, 3 approvers (finance, legal, ceo-tier), 1 requester
 *  - a "Purchase Request" workflow demonstrating conditional routing
 *    and a parallel ("all") approval stage
 *
 * Usage: php database/seed.php
 */

require __DIR__ . '/../src/Core/Autoloader.php';

use App\Core\Env;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowStep;

Env::load(__DIR__ . '/../.env');

function ensureUser(string $name, string $email, string $password, string $role): array
{
    $existing = User::findByEmail($email);
    if ($existing) {
        echo "User already exists: $email\n";
        return $existing;
    }
    $id = User::create($name, $email, $password, $role);
    echo "Created user: $email (role: $role, password: $password)\n";
    return User::find($id);
}

$admin = ensureUser('System Administrator', 'admin@itec.rw', 'AdminPass123', 'admin');
$finance = ensureUser('Alice Finance', 'finance@itec.rw', 'ApproverPass123', 'approver');
$legal = ensureUser('Bob Legal', 'legal@itec.rw', 'ApproverPass123', 'approver');
$ceo = ensureUser('Carol CEO', 'ceo@itec.rw', 'ApproverPass123', 'approver');
$requester = ensureUser('Dan Requester', 'requester@itec.rw', 'RequesterPass123', 'requester');

// Demo workflow: Purchase Request
// Step 1: Finance approval, always required.
// Step 2: Legal approval, only if amount > 10,000 (conditional routing).
// Step 3: Finance + Legal + CEO must ALL approve if amount > 50,000 (parallel/"all").
// Step 4: CEO sign-off, always required, as the final stage.
$existingWorkflows = array_filter(Workflow::all(), fn($w) => $w['name'] === 'Purchase Request');
if ($existingWorkflows) {
    echo "Workflow 'Purchase Request' already exists, skipping.\n";
} else {
    $workflowId = Workflow::create(
        'Purchase Request',
        'Approval workflow for company purchase requests, with amount-based conditional routing.',
        (int) $admin['id']
    );

    WorkflowStep::create($workflowId, 1, 'Finance Review', 'approver', null, 'single', []);
    WorkflowStep::create($workflowId, 2, 'Legal Review (large purchases)', 'approver', null, 'single', [
        ['field' => 'amount', 'operator' => '>', 'value' => 10000],
    ]);
    WorkflowStep::create($workflowId, 3, 'Executive Approval (very large purchases)', 'approver', null, 'all', [
        ['field' => 'amount', 'operator' => '>', 'value' => 50000],
    ]);

    echo "Created workflow: Purchase Request (id: $workflowId) with 3 steps.\n";
}

echo "\nSeed complete. Log in with any of the emails above (see passwords printed on first run).\n";
