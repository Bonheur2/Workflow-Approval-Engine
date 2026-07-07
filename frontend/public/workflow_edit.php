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
$pageTitle = 'Edit ' . $workflow['name'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payload = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'status' => $_POST['status'] ?? 'active',
    ];
    $update = api_put("workflows/$id", $payload, $token);
    if ($update['status'] === 200) {
        flash_success('Workflow updated.');
        redirect("workflow_show.php?id=$id");
    } else {
        $error = $update['data']['message'] ?? 'Update failed.';
    }
}

require __DIR__ . '/../templates/header.php';
?>

<h1>Edit workflow</h1>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <form method="post">
    <label>Name</label>
    <input type="text" name="name" required value="<?= e($_POST['name'] ?? $workflow['name']) ?>">
    <label>Description</label>
    <textarea name="description" rows="3"><?= e($_POST['description'] ?? $workflow['description']) ?></textarea>
    <label>Status</label>
    <select name="status">
      <option value="active" <?= $workflow['status'] === 'active' ? 'selected' : '' ?>>Active (accepts new submissions)</option>
      <option value="inactive" <?= $workflow['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (draft / retired)</option>
    </select>
    <p style="margin-top:16px;">
      <button type="submit" class="btn btn-primary">Save changes</button>
      <a href="workflow_show.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
    </p>
  </form>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
