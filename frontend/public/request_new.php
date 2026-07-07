<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$token = current_token();
$user = current_user();
$workflowId = (int) ($_GET['workflow_id'] ?? $_POST['workflow_id'] ?? 0);

$workflows = api_get('workflows', $token)['data']['data'] ?? [];
$activeWorkflows = array_filter($workflows, fn($w) => $w['status'] === 'active');

/**
 * Each active workflow's distinct condition field names (e.g. "day",
 * "amount"), so the form can show clearly-labeled value inputs for exactly
 * what the workflow's steps actually check instead of asking the
 * requester to type field names themselves.
 */
$workflowFields = [];
foreach ($activeWorkflows as $w) {
    $detail = api_get('workflows/' . (int) $w['id'], $token)['data']['data'] ?? null;
    $fields = [];
    foreach ($detail['steps'] ?? [] as $step) {
        foreach ($step['conditions'] ?? [] as $condition) {
            if (!empty($condition['field']) && !in_array($condition['field'], $fields, true)) {
                $fields[] = $condition['field'];
            }
        }
    }
    $workflowFields[$w['id']] = $fields;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $knownFields = $workflowFields[(int) $_POST['workflow_id']] ?? [];
    $data = collect_known_fields_from_post($_POST, count($knownFields));
    $data['requester_name'] = trim($_POST['requester_name'] ?? '');

    $result = api_post('requests', ['workflow_id' => (int) $_POST['workflow_id'], 'data' => $data], $token);
    if ($result['status'] === 201) {
        flash_success('Request submitted.');
        redirect('request_show.php?id=' . (int) $result['data']['data']['id']);
    } else {
        $error = $result['data']['message'] ?? 'Could not submit request.';
        $workflowId = (int) $_POST['workflow_id'];
    }
}

$knownFieldsForSelected = $workflowFields[$workflowId] ?? [];

$pageTitle = 'Submit a request';
require __DIR__ . '/../templates/header.php';
?>

<h1>Submit a request</h1>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <form method="post">
    <label>Workflow</label>
    <select name="workflow_id" id="workflow_id" required>
      <option value="">-- choose a workflow --</option>
      <?php foreach ($activeWorkflows as $w): ?>
        <option value="<?= (int) $w['id'] ?>" <?= $workflowId === (int) $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
      <?php endforeach; ?>
    </select>

    <label for="requester_name">Full name</label>
    <input type="text" id="requester_name" name="requester_name" required value="<?= e($_POST['requester_name'] ?? $user['name']) ?>">

    <h3>Request details</h3>
    <div class="field-list" id="known-fields">
      <?php render_known_fields($knownFieldsForSelected, [], $_POST); ?>
    </div>

    <p style="margin-top:16px;"><button type="submit" class="btn btn-primary">Submit request</button></p>
  </form>
</div>

<script>
window.WORKFLOW_FIELDS = <?= json_encode($workflowFields, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
(function () {
  var select = document.getElementById('workflow_id');
  var container = document.getElementById('known-fields');
  if (!select || !container) return;

  function humanize(name) {
    return name.replace(/[_-]/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
  }

  function render(fields) {
    container.innerHTML = '';
    if (!fields.length) {
      var msg = document.createElement('p');
      msg.className = 'empty';
      msg.textContent = 'Select a workflow above to see which fields it needs.';
      container.appendChild(msg);
      return;
    }
    fields.forEach(function (name, idx) {
      var n = idx + 1;
      var item = document.createElement('div');
      item.className = 'field-item';

      var label = document.createElement('label');
      label.textContent = humanize(name);

      var hidden = document.createElement('input');
      hidden.type = 'hidden';
      hidden.name = 'known_key_' + n;
      hidden.value = name;

      var value = document.createElement('input');
      value.type = 'text';
      value.name = 'known_value_' + n;
      value.placeholder = 'Enter ' + name;

      item.appendChild(label);
      item.appendChild(hidden);
      item.appendChild(value);
      container.appendChild(item);
    });
  }

  select.addEventListener('change', function () {
    render(window.WORKFLOW_FIELDS[select.value] || []);
  });
})();
</script>

<?php require __DIR__ . '/../templates/footer.php'; ?>
