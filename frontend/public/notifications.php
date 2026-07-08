<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$token = current_token();
$pageTitle = 'Notifications';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_read') {
    api_patch('notifications/' . (int) $_POST['id'] . '/read', [], $token);
    redirect('notifications.php');
}

$showUnreadOnly = isset($_GET['unread']);
$notifications = api_get('notifications' . ($showUnreadOnly ? '?unread=1' : ''), $token)['data']['data'] ?? [];

require __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
  <h1>Notifications</h1>
  <p style="margin:0;">
    <a href="notifications.php" class="btn btn-sm <?= !$showUnreadOnly ? 'btn-primary' : 'btn-secondary' ?>">All</a>
    <a href="notifications.php?unread=1" class="btn btn-sm <?= $showUnreadOnly ? 'btn-primary' : 'btn-secondary' ?>">Unread only</a>
  </p>
</div>

<div class="card">
  <?php if (empty($notifications)): ?>
    <?php render_empty_state('Nothing here.'); ?>
  <?php else: ?>
    <table>
      <tr><th>Notification</th><th>Type</th><th>When</th><th></th></tr>
      <?php foreach ($notifications as $n): ?>
        <tr class="<?= $n['is_read'] ? '' : 'row-unread' ?>">
          <td><?= $n['is_read'] ? '<span class="muted">' . e($n['message']) . '</span>' : '<strong>' . e($n['message']) . '</strong>' ?></td>
          <td class="small muted"><?= e($n['type']) ?></td>
          <td class="small muted"><?= e($n['created_at']) ?></td>
          <td class="actions-cell">
            <?php if (!empty($n['request_id'])): ?>
              <a href="request_show.php?id=<?= (int) $n['request_id'] ?>" class="btn btn-primary btn-sm">View request</a>
            <?php endif; ?>
            <?php if (!$n['is_read']): ?>
              <form method="post" style="display:inline;">
                <input type="hidden" name="action" value="mark_read">
                <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
                <button type="submit" class="btn btn-secondary btn-sm">Mark read</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
