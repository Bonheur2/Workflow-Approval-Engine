<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_role('approver', 'admin');

$token = current_token();
$pageTitle = 'My approval queue';
$requests = api_get('approvals', $token)['data']['data'] ?? [];

require __DIR__ . '/../templates/header.php';
?>

<h1>My approval queue</h1>
<p class="hint">Includes requests assigned directly to you, plus anything you can act on via an active delegation.</p>

<div class="card">
  <?php render_requests_table($requests, emptyMessage: "You're all caught up \u{2014} nothing needs your approval right now."); ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
