<?php
require_once __DIR__ . '/../src/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $result = api_post('auth/login', ['email' => $email, 'password' => $password]);

    if ($result['status'] === 200 && !empty($result['data']['data']['token'])) {
        log_in($result['data']['data']['token'], $result['data']['data']['user']);
        redirect('index.php');
    } else {
        $error = $result['data']['message'] ?? 'Login failed.';
    }
}

$pageTitle = 'Log in';
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
    <h1>Log in</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
      <p style="margin-top:18px;">
        <button type="submit" class="btn btn-primary">Log in</button>
      </p>
    </form>
    <p class="hint">No account? <a href="register.php">Register as a requester</a>.</p>
  </div>
</div>
</body>
</html>
