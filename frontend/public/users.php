<?php
require_once __DIR__ . '/../src/bootstrap.php';
require_role('admin');

$token = current_token();
$pageTitle = 'Users';
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $result = api_post('users', [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'role' => $_POST['role'] ?? 'requester',
        ], $token);
        if ($result['status'] === 201) {
            flash_success('User created.');
        } else {
            $error = $result['data']['message'] ?? 'Could not create user.';
        }
    } elseif ($action === 'role') {
        $uid = (int) $_POST['user_id'];
        $result = api_patch("users/$uid/role", ['role' => $_POST['role']], $token);
        if ($result['status'] === 200) {
            flash_success('Role updated.');
        } else {
            $error = $result['data']['message'] ?? 'Could not update role.';
        }
    } elseif ($action === 'status') {
        $uid = (int) $_POST['user_id'];
        $isActive = $_POST['is_active'] === '1';
        $result = api_patch("users/$uid/status", ['is_active' => $isActive], $token);
        if ($result['status'] === 200) {
            flash_success('Status updated.');
        } else {
            $error = $result['data']['message'] ?? 'Could not update status.';
        }
    }

    if (!$error) {
        redirect('users.php');
    }
}

$users = api_get('users', $token)['data']['data'] ?? [];
require __DIR__ . '/../templates/header.php';
?>

<h1>Users</h1>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="card">
  <table>
    <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($users as $u): ?>
    <tr>
      <td><?= e($u['name']) ?></td>
      <td><?= e($u['email']) ?></td>
      <td><?= e($u['role']) ?></td>
      <td><?= render_badge($u['is_active'] ? 'active' : 'inactive') ?></td>
      <td class="actions-cell">
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="role">
          <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
          <select name="role" style="width:auto; display:inline-block; padding:3px 6px;">
            <?php foreach (['requester', 'approver', 'admin'] as $r): ?>
              <option value="<?= $r ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-secondary btn-sm">Update role</button>
        </form>
        <form method="post" style="display:inline;">
          <input type="hidden" name="action" value="status">
          <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
          <input type="hidden" name="is_active" value="<?= $u['is_active'] ? '0' : '1' ?>">
          <button type="submit" class="btn btn-secondary btn-sm"><?= $u['is_active'] ? 'Deactivate' : 'Activate' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>

<div class="card">
  <h2>Create a user</h2>
  <form method="post">
    <input type="hidden" name="action" value="create">
    <div class="row">
      <div>
        <label>Name</label>
        <input type="text" name="name" required>
      </div>
      <div>
        <label>Email</label>
        <input type="email" name="email" required>
      </div>
    </div>
    <div class="row">
      <div>
        <label>Password</label>
        <input type="password" name="password" required minlength="8">
      </div>
      <div>
        <label>Role</label>
        <select name="role">
          <option value="requester">requester</option>
          <option value="approver">approver</option>
          <option value="admin">admin</option>
        </select>
      </div>
    </div>
    <p style="margin-top:16px;"><button type="submit" class="btn btn-primary">Create user</button></p>
  </form>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
