<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$token = current_token();
$id = (int) ($_GET['id'] ?? 0);
$result = api_get("workflows/$id", $token);

if ($result['status'] !== 200) {
    flash_error($result['data']['message'] ?? 'Workflow not found.');
    redirect('workflows.php');
}

$workflow = $result['data']['data'];
$pageTitle = $workflow['name'];

require __DIR__ . '/../templates/header.php';
?>

<h1><?= e($workflow['name']) ?> <?= render_badge($workflow['status']) ?></h1>
<p class="muted"><?= e($workflow['description']) ?></p>
<p class="small muted">Version <?= (int) $workflow['version'] ?> &middot; created <?= e($workflow['created_at']) ?></p>

<div class="card">
  <h2>Approval steps</h2>
  <?php if (empty($workflow['steps'])): ?>
    <p class="empty">No steps configured.</p>
  <?php else: ?>
    <table>
      <tr><th>Order</th><th>Name</th><th>Approver</th><th>Type</th><th>Conditions</th></tr>
      <?php foreach ($workflow['steps'] as $step): ?>
      <tr>
        <td><?= (int) $step['step_order'] ?></td>
        <td><?= e($step['name']) ?></td>
        <td><?= $step['approver_user_id'] ? 'User #' . (int) $step['approver_user_id'] : 'Role: ' . e($step['approver_role']) ?></td>
        <td><?= e($step['approval_type']) ?></td>
        <td class="small">
          <?php if (empty($step['conditions'])): ?>
            <span class="muted">always applies</span>
          <?php else: ?>
            <?php foreach ($step['conditions'] as $c): ?>
              <code><?= e($c['field']) ?> <?= e($c['operator']) ?> <?= e(is_array($c['value']) ? implode(',', $c['value']) : (string)$c['value']) ?></code><br>
            <?php endforeach; ?>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>

  <?php if (has_role('admin')): ?>
    <p style="margin-top:14px;">
      <a href="workflow_edit_steps.php?id=<?= (int) $workflow['id'] ?>" class="btn btn-secondary btn-sm">Edit steps</a>
      <a href="workflow_edit.php?id=<?= (int) $workflow['id'] ?>" class="btn btn-secondary btn-sm">Edit details</a>
    </p>
    <p class="hint">Editing steps only affects future submissions - requests already in progress keep the step definitions they were submitted against.</p>
  <?php endif; ?>
</div>

<?php if ($workflow['status'] === 'active'): ?>
  <p><a href="request_new.php?workflow_id=<?= (int) $workflow['id'] ?>" class="btn btn-primary">Submit a request against this workflow</a></p>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
