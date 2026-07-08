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

<h1>Notifications</h1>
<p>
  <a href="notifications.php" class="btn btn-sm <?= !$showUnreadOnly ? 'btn-primary' : 'btn-secondary' ?>">All</a>
  <a href="notifications.php?unread=1" class="btn btn-sm <?= $showUnreadOnly ? 'btn-primary' : 'btn-secondary' ?>">Unread only</a>
</p>

<div class="card">
  <?php if (empty($notifications)): ?>
    <p class="empty">Nothing here.</p>
  <?php else: ?>
    <ul class="list-plain">
      <?php foreach ($notifications as $n): ?>
        <li>
          <?php if (!$n['is_read']): ?><strong><?= e($n['message']) ?></strong><?php else: ?><span class="muted"><?= e($n['message']) ?></span><?php endif; ?>
          <br><span class="small muted"><?= e($n['type']) ?> &middot; <?= e($n['created_at']) ?></span>
          <?php if (!empty($n['request_id'])): ?>
            <a href="request_show.php?id=<?= (int) $n['request_id'] ?>" class="btn btn-primary btn-sm" style="margin-left:8px;">View request</a>
          <?php endif; ?>
          <?php if (!$n['is_read']): ?>
            <form method="post" style="display:inline;">
              <input type="hidden" name="action" value="mark_read">
              <input type="hidden" name="id" value="<?= (int) $n['id'] ?>">
              <button type="submit" class="btn btn-secondary btn-sm" style="margin-left:8px;">Mark read</button>
            </form>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
