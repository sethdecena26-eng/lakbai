<?php
require_once __DIR__ . '/config/app.php';

if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . '/modules/dashboard/index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');
    if ($email && $password) {
        $auth = new Auth();
        if ($auth->login($email, $password)) {
            redirect(APP_URL . '/modules/dashboard/index.php');
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= APP_NAME ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">
  <style>
    body { display: block; }
    .login-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; }
  </style>
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-logo">LS</div>
    <h1 class="login-title"><?= APP_NAME ?></h1>
    <p class="login-subtitle">Sign in</p>

    <?php if ($error): ?>
      <div class="alert alert-danger">⚠ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="form-group">
        <label class="form-label" for="email">Email Address</label>
        <input type="email" id="email" name="email" class="form-control"
               placeholder="admin@companyx.com"
               value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-group">
        <label class="form-label" for="password">Password</label>
        <input type="password" id="password" name="password" class="form-control"
               placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary w-100" style="justify-content:center;padding:11px;">
        Sign In →
      </button>
    </form>

    
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
