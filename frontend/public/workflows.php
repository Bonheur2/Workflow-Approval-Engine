<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$token = current_token();
$pageTitle = 'Workflows';
$workflows = api_get('workflows', $token)['data']['data'] ?? [];

require __DIR__ . '/../templates/header.php';
?>

<div class="page-header">
  <h1>Workflows</h1>
  <?php if (has_role('admin') && !empty($workflows)): ?>
    <a href="workflow_new.php" class="btn btn-success">+ New workflow</a>
  <?php endif; ?>
</div>

<div class="card">
  <?php render_workflows_table(
      $workflows,
      showDescription: true,
      emptyMessage: 'No workflows yet.',
      emptyActionLabel: has_role('admin') ? 'Create a workflow' : null,
      emptyActionUrl: has_role('admin') ? 'workflow_new.php' : null,
  ); ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
