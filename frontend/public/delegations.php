<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_role('approver', 'admin');

$token = current_token();
$user = current_user();
$pageTitle = 'Delegation';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $result = api_post('delegations', [
            'delegate_id' => (int) $_POST['delegate_id'],
            'start_date' => $_POST['start_date'],
            'end_date' => $_POST['end_date'],
        ], $token);
        if ($result['status'] === 201) {
            flash_success('Delegation created.');
        } else {
            $error = $result['data']['message'] ?? 'Could not create delegation.';
        }
    } elseif ($action === 'revoke') {
        $result = api_delete('delegations/' . (int) $_POST['id'], $token);
        if ($result['status'] === 200) {
            flash_success('Delegation revoked.');
        } else {
            $error = $result['data']['message'] ?? 'Could not revoke delegation.';
        }
    }

    if (!$error) {
        redirect('delegations.php');
    }
}

$users = api_get('users', $token)['data']['data'] ?? [];
$candidates = array_filter($users, fn($u) => in_array($u['role'], ['approver', 'admin'], true) && $u['is_active'] && (int) $u['id'] !== (int) $user['id']);
$myDelegations = api_get('delegations', $token)['data']['data'] ?? [];

require __DIR__ . '/../templates/header.php';
?>

<h1>Delegation</h1>
<p class="hint">Delegate your approval responsibilities to another authorized user for a specific date range. Delegated approvals stay fully traceable in the request's approval history.</p>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <h2>Your delegations</h2>
  <?php if (empty($myDelegations)): ?>
    <p class="empty">You haven't created any delegations.</p>
  <?php else: ?>
    <table>
      <tr><th>Delegate</th><th>Start</th><th>End</th><th>Status</th><th></th></tr>
      <?php foreach ($myDelegations as $d): ?>
      <tr>
        <td>User #<?= (int) $d['delegate_id'] ?></td>
        <td><?= e($d['start_date']) ?></td>
        <td><?= e($d['end_date']) ?></td>
        <td><?= render_badge($d['active'] ? 'active' : 'inactive', $d['active'] ? 'active' : 'revoked') ?></td>
        <td>
          <?php if ($d['active']): ?>
          <form method="post">
            <input type="hidden" name="action" value="revoke">
            <input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
            <button type="submit" class="btn btn-danger btn-sm">Revoke</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Create a new delegation</h2>
  <form method="post">
    <input type="hidden" name="action" value="create">
    <label>Delegate to</label>
    <select name="delegate_id" required>
      <option value="">-- choose a person --</option>
      <?php foreach ($candidates as $u): ?>
        <option value="<?= (int) $u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <div class="row">
      <div>
        <label>Start date</label>
        <input type="date" name="start_date" required value="<?= date('Y-m-d') ?>">
      </div>
      <div>
        <label>End date</label>
        <input type="date" name="end_date" required value="<?= date('Y-m-d') ?>">
      </div>
    </div>
    <p style="margin-top:16px;"><button type="submit" class="btn btn-primary">Create delegation</button></p>
  </form>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
