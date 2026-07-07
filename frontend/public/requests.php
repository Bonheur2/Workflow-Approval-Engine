<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$token = current_token();
$pageTitle = has_role('admin') ? 'All requests' : 'My requests';
$requests = api_get('requests', $token)['data']['data'] ?? [];
usort($requests, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

require __DIR__ . '/../templates/header.php';
?>

<h1><?= e($pageTitle) ?></h1>

<div class="card">
  <?php render_requests_table($requests); ?>
</div>

<p><a href="workflows.php" class="btn btn-primary">Submit a new request</a></p>

<?php require __DIR__ . '/../templates/footer.php'; ?>
