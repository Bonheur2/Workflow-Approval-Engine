<?php

require __DIR__ . '/../src/Core/Autoloader.php';

use App\Controllers\ApprovalController;
use App\Controllers\AuditController;
use App\Controllers\AuthController;
use App\Controllers\DelegationController;
use App\Controllers\NotificationController;
use App\Controllers\RequestController;
use App\Controllers\UserController;
use App\Controllers\WorkflowController;
use App\Core\Env;
use App\Core\Request;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;

Env::load(__DIR__ . '/../.env');

// CORS: permissive by default so the API is easy to exercise from any
// client/tool during evaluation. Tighten Access-Control-Allow-Origin in
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router = new Router();
$auth = AuthMiddleware::handle();

// ---------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->get('/api/auth/me', [AuthController::class, 'me'], [$auth]);

// ---------------------------------------------------------------------
// User management (admin only)
// ---------------------------------------------------------------------
$router->get('/api/users', [UserController::class, 'index'], [$auth, RoleMiddleware::handle('admin')]);
$router->post('/api/users', [UserController::class, 'store'], [$auth, RoleMiddleware::handle('admin')]);
$router->get('/api/users/{id}', [UserController::class, 'show'], [$auth, RoleMiddleware::handle('admin')]);
$router->patch('/api/users/{id}/role', [UserController::class, 'updateRole'], [$auth, RoleMiddleware::handle('admin')]);
$router->patch('/api/users/{id}/status', [UserController::class, 'updateStatus'], [$auth, RoleMiddleware::handle('admin')]);

// ---------------------------------------------------------------------
// Workflow management
// (read = any authenticated user; write = admin only)
// ---------------------------------------------------------------------
$router->get('/api/workflows', [WorkflowController::class, 'index'], [$auth]);
$router->get('/api/workflows/{id}', [WorkflowController::class, 'show'], [$auth]);
$router->post('/api/workflows', [WorkflowController::class, 'store'], [$auth, RoleMiddleware::handle('admin')]);
$router->put('/api/workflows/{id}', [WorkflowController::class, 'update'], [$auth, RoleMiddleware::handle('admin')]);
$router->put('/api/workflows/{id}/steps', [WorkflowController::class, 'replaceSteps'], [$auth, RoleMiddleware::handle('admin')]);

// ---------------------------------------------------------------------
// Requests (submission + lifecycle)
// ---------------------------------------------------------------------
$router->post('/api/requests', [RequestController::class, 'store'], [$auth]);
$router->get('/api/requests', [RequestController::class, 'index'], [$auth]);
$router->get('/api/requests/{id}', [RequestController::class, 'show'], [$auth]);
$router->post('/api/requests/{id}/resubmit', [RequestController::class, 'resubmit'], [$auth]);
$router->get('/api/requests/{id}/history', [AuditController::class, 'show'], [$auth]);

// ---------------------------------------------------------------------
// Approval actions
// ---------------------------------------------------------------------
$router->get('/api/approvals', [ApprovalController::class, 'myQueue'], [$auth]);
$router->post('/api/requests/{id}/approve', [ApprovalController::class, 'approve'], [$auth, RoleMiddleware::handle('approver', 'admin')]);
$router->post('/api/requests/{id}/reject', [ApprovalController::class, 'reject'], [$auth, RoleMiddleware::handle('approver', 'admin')]);
$router->post('/api/requests/{id}/return', [ApprovalController::class, 'returnRequest'], [$auth, RoleMiddleware::handle('approver', 'admin')]);

// ---------------------------------------------------------------------
// Delegation
// ---------------------------------------------------------------------
$router->post('/api/delegations', [DelegationController::class, 'store'], [$auth, RoleMiddleware::handle('approver', 'admin')]);
$router->get('/api/delegations', [DelegationController::class, 'index'], [$auth, RoleMiddleware::handle('approver', 'admin')]);
$router->delete('/api/delegations/{id}', [DelegationController::class, 'revoke'], [$auth, RoleMiddleware::handle('approver', 'admin')]);

// ---------------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------------
$router->get('/api/notifications', [NotificationController::class, 'index'], [$auth]);
$router->patch('/api/notifications/{id}/read', [NotificationController::class, 'markRead'], [$auth]);

// ---------------------------------------------------------------------
// Health check
// ---------------------------------------------------------------------
$router->get('/api/health', function () {
    \App\Core\Response::success(['status' => 'ok', 'time' => date('c')]);
});

$router->dispatch(new Request());
