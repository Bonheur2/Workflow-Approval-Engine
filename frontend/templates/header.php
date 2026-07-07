<?php
/** @var string $pageTitle */
$user = current_user();
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Workflow Engine') ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="topbar">
  <div class="brand">Workflow &amp; Approval Engine</div>
  <?php if ($user): ?>
  <nav>
    <a href="index.php" class="<?= (basename($_SERVER['PHP_SELF']) === 'index.php') ? 'active' : '' ?>">Dashboard</a>
    <a href="workflows.php">Workflows</a>
    <a href="requests.php">Requests</a>
    <?php if (has_role('approver', 'admin')): ?>
      <a href="approvals.php">My Approvals</a>
      <a href="delegations.php">Delegation</a>
    <?php endif; ?>
    <?php if (has_role('admin')): ?>
      <a href="users.php">Users</a>
    <?php endif; ?>
    <a href="notifications.php">Notifications</a>
    <span class="user-chip"><?= e($user['name']) ?><span class="role-badge"><?= e($user['role']) ?></span></span>
    <a href="logout.php">Log out</a>
  </nav>
  <?php endif; ?>
</div>
<div class="container">
  <?php if ($flash['error']): ?><div class="alert alert-error"><?= e($flash['error']) ?></div><?php endif; ?>
  <?php if ($flash['success']): ?><div class="alert alert-success"><?= e($flash['success']) ?></div><?php endif; ?>
