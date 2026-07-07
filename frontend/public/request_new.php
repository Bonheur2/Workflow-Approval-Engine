<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$token = current_token();
$workflowId = (int) ($_GET['workflow_id'] ?? $_POST['workflow_id'] ?? 0);

$workflows = api_get('workflows', $token)['data']['data'] ?? [];
$activeWorkflows = array_filter($workflows, fn($w) => $w['status'] === 'active');

$error = null;
$dataRowCount = 8;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = collect_kv_from_post($_POST, $dataRowCount);
    $result = api_post('requests', ['workflow_id' => (int) $_POST['workflow_id'], 'data' => $data], $token);
    if ($result['status'] === 201) {
        flash_success('Request submitted.');
        redirect('request_show.php?id=' . (int) $result['data']['data']['id']);
    } else {
        $error = $result['data']['message'] ?? 'Could not submit request.';
        $workflowId = (int) $_POST['workflow_id'];
    }
}

$pageTitle = 'Submit a request';
require __DIR__ . '/../templates/header.php';
?>

<h1>Submit a request</h1>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <form method="post">
    <label>Workflow</label>
    <select name="workflow_id" required>
      <option value="">-- choose a workflow --</option>
      <?php foreach ($activeWorkflows as $w): ?>
        <option value="<?= (int) $w['id'] ?>" <?= $workflowId === (int) $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <h3>Request details</h3>
    <p class="hint">Enter whatever fields your workflow's steps use for conditional routing (e.g. <code>amount</code>, <code>department</code>, <code>country</code>). Numbers are detected automatically. Leave rows blank if unused.</p>

    <?php render_kv_rows($dataRowCount, [], $_POST); ?>

    <p style="margin-top:16px;"><button type="submit" class="btn btn-primary">Submit request</button></p>
  </form>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
