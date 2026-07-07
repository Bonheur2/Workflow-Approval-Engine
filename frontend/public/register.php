<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = api_post('auth/register', ['name' => $name, 'email' => $email, 'password' => $password]);

    if ($result['status'] === 201) {
        flash_success('Account created. You can now log in.');
        redirect('login.php');
    } else {
        $error = $result['data']['message'] ?? 'Registration failed.';
        if (!empty($result['data']['errors'])) {
            $error .= ' ' . implode(' ', array_merge(...array_values($result['data']['errors'])));
        }
    }
}

$pageTitle = 'Register';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<div class="container" style="max-width: 420px; margin-top: 80px;">
  <div class="card">
    <h1>Create an account</h1>
    <p class="hint">Self-registration always creates a <code>requester</code> account. Ask an administrator to grant approver/admin access afterward.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <label for="name">Full name</label>
      <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      <label for="password">Password (min 8 characters)</label>
      <input type="password" id="password" name="password" required minlength="8">
      <p style="margin-top:18px;">
        <button type="submit" class="btn btn-primary">Create account</button>
      </p>
    </form>
    <p class="hint"><a href="login.php">Back to log in</a></p>
  </div>
</div>
</body>
</html>
