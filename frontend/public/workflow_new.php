<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_role('admin');

$token = current_token();
$pageTitle = 'New workflow';

$stepsCount = isset($_GET['steps_count']) ? max(1, min(20, (int) $_GET['steps_count'])) : null;
$error = null;

// Fetch users so the admin can optionally pin a step to one specific person.
$users = api_get('users', $token)['data']['data'] ?? [];
$approverUsers = array_values(array_filter($users, fn($u) => in_array($u['role'], ['approver', 'admin'], true) && $u['is_active']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $count = (int) $_POST['steps_count'];
    $payload = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'steps' => collect_steps_from_post($_POST, $count),
    ];

    $result = api_post('workflows', $payload, $token);

    if ($result['status'] === 201) {
        flash_success('Workflow created.');
        redirect('workflow_show.php?id=' . (int) $result['data']['data']['id']);
    } else {
        $error = $result['data']['message'] ?? 'Could not create workflow.';
        if (!empty($result['data']['errors'])) {
            $error .= ' ' . json_encode($result['data']['errors']);
        }
        $stepsCount = $count; // re-render step 2 with what was submitted, so nothing is lost
    }
}

require __DIR__ . '/../templates/header.php';
?>

<h1>New workflow</h1>

<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<?php if ($stepsCount === null): ?>
  <!-- Step 1: how many approval steps will this workflow need? -->
  <div class="card">
    <form method="get" action="workflow_new.php">
      <label for="steps_count">Number of approval steps</label>
      <input type="number" id="steps_count" name="steps_count" min="1" max="20" value="3" required>
      <p class="hint">You'll name the workflow and define each step on the next screen.</p>
      <p style="margin-top:16px;"><button type="submit" class="btn btn-primary">Continue &rarr;</button></p>
    </form>
  </div>
<?php else: ?>
  <!-- Step 2: workflow details + one render_step_fields() block per step -->
  <form method="post">
    <input type="hidden" name="steps_count" value="<?= (int) $stepsCount ?>">
    <div class="card">
      <h2>Workflow details</h2>
      <label for="name">Name</label>
      <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Leave Request">
      <label for="description">Description</label>
      <textarea id="description" name="description" rows="2"><?= e($_POST['description'] ?? '') ?></textarea>
    </div>

    <?php for ($i = 1; $i <= $stepsCount; $i++): ?>
      <?php render_step_fields($i, null, $approverUsers, $_POST); ?>
    <?php endfor; ?>

    <p><button type="submit" class="btn btn-primary">Create workflow</button>
    <a href="workflow_new.php" class="btn btn-secondary">Start over</a></p>
  </form>
<?php endif; ?>

<?php require __DIR__ . '/../templates/footer.php'; ?>
