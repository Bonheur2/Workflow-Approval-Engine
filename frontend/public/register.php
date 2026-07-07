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
<div class="auth-page">
  <div class="auth-card">
    <!-- <div class="auth-logo">
      <span class="brand-mark">W</span>
      <span class="auth-logo-text">Workflow &amp; Approval Engine</span>
    </div> -->
    <h1 class="auth-title">Create your account</h1>
    <p class="auth-subtitle">Self-registration creates a requester account, ask an administrator to grant approver/admin access afterward.</p>

    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="auth-form">
      <label for="name">Full name</label>
      <input type="text" id="name" name="name" required value="<?= e($_POST['name'] ?? '') ?>" placeholder="Jane Doe">

      <label for="email">Email</label>
      <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="you@example.com">

      <label for="password">Password (min 8 characters)</label>
      <div class="password-field">
        <input type="password" id="password" name="password" required minlength="8">
        <button type="button" class="password-toggle" data-target="password">Show</button>
      </div>

      <button type="submit" class="btn btn-primary btn-block" style="margin-top:22px;">Create account</button>
    </form>

    <p class="hint" style="text-align:center; margin-top:20px;">Already have an account? <a class="auth-link" href="login.php">Log in</a></p>
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
