<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_login();

$token = current_token();
$user = current_user();
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $comments = trim($_POST['comments'] ?? '') ?: null;

    if (in_array($action, ['approve', 'reject', 'return'], true)) {
        $result = api_post("requests/$id/$action", ['comments' => $comments], $token);
        flash_result($result, "Action '$action' recorded.");
        redirect("request_show.php?id=$id");
    }

    if ($action === 'resubmit') {
        $data = collect_kv_from_post($_POST);
        $result = api_post("requests/$id/resubmit", ['data' => $data], $token);
        flash_result($result, 'Request resubmitted.');
        redirect("request_show.php?id=$id");
    }
}

$result = api_get("requests/$id", $token);
if ($result['status'] !== 200) {
    flash_error($result['data']['message'] ?? 'Request not found.');
    redirect('requests.php');
}
$req = $result['data']['data'];
$pageTitle = "Request #$id";

$isOwner = (int) $req['requester_id'] === (int) $user['id'];
$canAct = has_role('approver', 'admin') && $req['status'] === 'pending';

require __DIR__ . '/../templates/header.php';
?>

<h1>Request #<?= (int) $req['id'] ?> <?= render_badge($req['status']) ?></h1>
<p class="muted small">Workflow #<?= (int) $req['workflow_id'] ?> &middot; submitted <?= e($req['created_at']) ?> &middot; current step: <?= $req['current_step_order'] !== null ? (int) $req['current_step_order'] : '-' ?></p>

<div class="card">
  <h2>Request data</h2>
  <?php if (empty($req['data'])): ?>
    <p class="empty">No data submitted.</p>
  <?php else: ?>
    <table>
      <tr><th>Field</th><th>Value</th></tr>
      <?php foreach ($req['data'] as $k => $v): ?>
        <tr><td><?= e($k) ?></td><td><?= e(is_scalar($v) ? (string) $v : json_encode($v)) ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<?php if ($canAct): ?>
<div class="card">
  <h2>Take action</h2>
  <p class="hint">If you're not the assigned approver (or an active delegate) for the current step, the API will reject the action.</p>
  <form method="post">
    <label>Comments (optional)</label>
    <textarea name="comments" rows="2"></textarea>
    <p style="margin-top:14px;">
      <button type="submit" name="action" value="approve" class="btn btn-success">Approve</button>
      <button type="submit" name="action" value="reject" class="btn btn-danger">Reject</button>
      <button type="submit" name="action" value="return" class="btn btn-secondary">Return for modification</button>
    </p>
  </form>
</div>
<?php endif; ?>

<?php if ($isOwner && $req['status'] === 'returned'): ?>
<div class="card">
  <h2>Edit and resubmit</h2>
  <p class="hint">This request was returned for modification. Fill in the corrected values and resubmit - evaluation restarts from the first step.</p>
  <form method="post">
    <input type="hidden" name="action" value="resubmit">
    <?php render_kv_rows($req['data'] ?? [], $_POST); ?>
    <p style="margin-top:14px;"><button type="submit" class="btn btn-primary">Resubmit</button></p>
  </form>
</div>
<?php endif; ?>

<div class="card">
  <h2>Approvals</h2>
  <?php render_approvals_table($req['approvals']); ?>
</div>

<div class="card">
  <h2>Audit trail</h2>
  <?php render_audit_trail($req['audit_trail']); ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
