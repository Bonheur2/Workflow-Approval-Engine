<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$token = current_token();
$user = current_user();
$pageTitle = 'Dashboard';

$myRequests = api_get('requests', $token)['data']['data'] ?? [];
$pendingApprovals = has_role('approver', 'admin') ? (api_get('approvals', $token)['data']['data'] ?? []) : [];
$unread = api_get('notifications?unread=1', $token)['data']['data'] ?? [];
$workflows = api_get('workflows', $token)['data']['data'] ?? [];

require __DIR__ . '/../templates/header.php';
?>

<h1>Welcome, <?= e($user['name']) ?></h1>

<div class="row">
  <div class="card">
    <h2><?= has_role('admin') ? 'All requests' : 'My requests' ?></h2>
    <p style="font-size: 28px; font-weight: 700; margin: 0;"><?= count($myRequests) ?></p>
    <p class="hint mt-0"><a href="requests.php">View all &rarr;</a></p>
  </div>

  <?php if (has_role('approver', 'admin')): ?>
  <div class="card">
    <h2>Awaiting your approval</h2>
    <p style="font-size: 28px; font-weight: 700; margin: 0;"><?= count($pendingApprovals) ?></p>
    <p class="hint mt-0"><a href="approvals.php">Review queue &rarr;</a></p>
  </div>
  <?php endif; ?>

  <div class="card">
    <h2>Unread notifications</h2>
    <p style="font-size: 28px; font-weight: 700; margin: 0;"><?= count($unread) ?></p>
    <p class="hint mt-0"><a href="notifications.php">View all &rarr;</a></p>
  </div>
</div>

<div class="card">
  <h2>Available workflows</h2>
  <?php render_workflows_table($workflows); ?>
  <?php if (has_role('admin')): ?>
    <p style="margin-top:14px;"><a href="workflow_new.php" class="btn btn-success btn-sm">+ New workflow</a></p>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
