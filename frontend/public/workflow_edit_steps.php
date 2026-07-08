<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_role('admin');

$token = current_token();
$id = (int) ($_GET['id'] ?? 0);
$result = api_get("workflows/$id", $token);
if ($result['status'] !== 200) {
    flash_error('Workflow not found.');
    redirect('workflows.php');
}
$workflow = $result['data']['data'];
$pageTitle = 'Edit steps: ' . $workflow['name'];
$error = null;

$users = api_get('users', $token)['data']['data'] ?? [];
$approverUsers = array_values(array_filter($users, fn($u) => in_array($u['role'], ['approver', 'admin'], true) && $u['is_active']));

$defaultCount = count($workflow['steps']) ?: 3;
$stepsCount = isset($_GET['steps_count']) ? max(1, min(20, (int) $_GET['steps_count'])) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = (int) $_POST['steps_count'];
    $update = api_put("workflows/$id/steps", ['steps' => collect_steps_from_post($_POST, $count)], $token);
    if ($update['status'] === 200) {
        flash_success('Steps updated. Requests already in progress keep their original step snapshot.');
        redirect("workflow_show.php?id=$id");
    } else {
        $error = $update['data']['message'] ?? 'Could not update steps.';
        $stepsCount = $count;
    }
}

require __DIR__ . '/../templates/header.php';
?>

<h1>Edit steps: <?= e($workflow['name']) ?></h1>
<p class="hint">This replaces the <strong>entire</strong> step list. Requests currently in progress are unaffected - they keep the step definitions they were submitted against.</p>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($stepsCount === null): ?>
  <div class="card">
    <p>Current step count: <strong><?= count($workflow['steps']) ?></strong></p>
    <form method="get">
      <input type="hidden" name="id" value="<?= (int) $id ?>">
      <label>How many steps should the new definition have?</label>
      <input type="number" name="steps_count" min="1" max="20" value="<?= $defaultCount ?>" required>
      <p style="margin-top:16px;"><button type="submit" class="btn btn-primary">Continue &rarr;</button></p>
    </form>
  </div>
<?php else: ?>
  <form method="post">
    <input type="hidden" name="steps_count" value="<?= (int) $stepsCount ?>">
    <?php for ($i = 1; $i <= $stepsCount; $i++): ?>
      <?php render_step_fields($i, $workflow['steps'][$i - 1] ?? null, $approverUsers, $_POST); ?>
    <?php endfor; ?>

    <p><button type="submit" class="btn btn-primary">Save steps</button>
    <a href="workflow_show.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a></p>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
