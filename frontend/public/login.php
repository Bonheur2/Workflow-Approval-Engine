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
<div class="auth-page">
  <div class="auth-card">
    <!-- <div class="auth-logo">
      <span class="brand-mark">W</span>
      <span class="auth-logo-text">Workflow &amp; Approval Engine</span>
    </div> -->
    <h1 class="auth-title">Welcome back</h1>
    <p class="auth-subtitle">Log in to manage workflows and approvals</p>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="auth-form">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com">

      <label for="password">Password</label>
      <div class="password-field">
        <input type="password" id="password" name="password" required>
        <button type="button" class="password-toggle" data-target="password">Show</button>
      </div>

      <p class="auth-forgot">
        <a href="#" onclick="event.preventDefault()" title="Contact an administrator to reset your password.">Forgot password?</a>
      </p>

      <div class="auth-row">
        <span>Remember sign-in details</span>
        <label class="switch">
          <input type="checkbox" name="remember">
          <span class="switch-slider"></span>
        </label>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Log in</button>
    </form>

    <p class="hint" style="text-align:center; margin-top:20px;">No account? <a class="auth-link" href="register.php">Register as a requester</a>.</p>
  </div>
</div>
<script>
document.querySelectorAll('.password-toggle').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var field = document.getElementById(btn.dataset.target);
    var showing = field.type === 'text';
    field.type = showing ? 'password' : 'text';
    btn.textContent = showing ? 'Show' : 'Hide';
  });
});
</script>
</body>
</html>
