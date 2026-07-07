<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$token = current_token();
$pageTitle = 'Workflows';
$workflows = api_get('workflows', $token)['data']['data'] ?? [];

require __DIR__ . '/../templates/header.php';
?>

<h1>Workflows</h1>

<div class="card">
  <?php render_workflows_table($workflows, showDescription: true); ?>
</div>

<?php if (has_role('admin')): ?>
  <p><a href="workflow_new.php" class="btn btn-success">+ New workflow</a></p>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
