<?php
/** @var string $pageTitle */
$user = current_user();
$flash = take_flash();
$currentPage = basename($_SERVER['PHP_SELF']);
$navActive = fn (string $file): string => $currentPage === $file ? 'active' : '';
$userInitial = $user ? strtoupper(substr(trim($user['name']), 0, 1)) : '?';
$unreadCount = $user ? count(api_get('notifications?unread=1', current_token())['data']['data'] ?? []) : 0;
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
<?php if ($user): ?>
<header class="topbar">
  <div class="topbar-left">
    <span class="brand-mark">W</span>
    <span class="brand-text">Workflow &amp; Approval Engine</span>
  </div>
  <div class="topbar-right">
    <a href="notifications.php" class="notif-bell <?= $navActive('notifications.php') ?>" title="Notifications" aria-label="Notifications">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
      <?php if ($unreadCount > 0): ?><span class="notif-badge"><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span><?php endif; ?>
    </a>
    <details class="user-menu">
      <summary class="user-trigger">
        <span class="user-avatar"><?= e($userInitial) ?></span>
        <span class="user-meta">
          <span class="user-name"><?= e($user['name']) ?></span>
          <span class="user-role"><?= e($user['role']) ?></span>
        </span>
        <span class="user-chevron">&#9660;</span>
      </summary>
      <div class="user-dropdown">
        <a href="logout.php">Log out</a>
      </div>
    </details>
  </div>
</header>
<aside class="sidebar">
  <nav class="sidebar-nav">
    <a href="index.php" class="<?= $navActive('index.php') ?>">Dashboard</a>
    <a href="workflows.php" class="<?= $navActive('workflows.php') ?>">Workflows</a>
    <a href="requests.php" class="<?= $navActive('requests.php') ?>">Requests</a>
    <?php if (has_role('approver', 'admin')): ?>
      <a href="approvals.php" class="<?= $navActive('approvals.php') ?>">My Approvals</a>
      <a href="delegations.php" class="<?= $navActive('delegations.php') ?>">Delegation</a>
    <?php endif; ?>
    <?php if (has_role('admin')): ?>
      <a href="users.php" class="<?= $navActive('users.php') ?>">Users</a>
    <?php endif; ?>
  </nav>
</aside>
<?php endif; ?>
<div class="container<?= $user ? ' with-sidebar' : '' ?>">
  <?php if ($flash['error']): ?><div class="alert alert-error"><?= e($flash['error']) ?></div><?php endif; ?>
  <?php if ($flash['success']): ?><div class="alert alert-success"><?= e($flash['success']) ?></div><?php endif; ?>
