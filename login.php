<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit;
}

$error = '';
$prefillEmail = $_SESSION['last_login_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && $user['status'] === 'active' && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_email'] = $user['email'];
        unset($_SESSION['last_login_email']);
        log_activity($pdo, 'Logged in');
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    } else {
        $error = 'Invalid email/password, or account is inactive.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login | <?= APP_NAME ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box}
body{background:#f4f6f8;color:#17212b;font-family:'DM Sans',sans-serif;min-height:100vh;margin:0;display:flex;align-items:center;justify-content:center;padding:24px;}
.login-shell{background:#fff;border:1px solid #e4e9ed;border-radius:18px;box-shadow:0 24px 70px rgba(23,33,43,.13);display:grid;grid-template-columns:minmax(280px,.9fr) minmax(360px,1.1fr);max-width:920px;min-height:540px;overflow:hidden;width:100%;}
.login-visual{background:#17212b;color:#fff;display:flex;flex-direction:column;justify-content:space-between;overflow:hidden;padding:42px;position:relative;}
.login-visual:before{background-image:linear-gradient(rgba(255,255,255,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.07) 1px,transparent 1px);background-size:32px 32px;content:'';inset:0;opacity:.5;position:absolute;}
.login-visual:after{background:#e56b4f;border-radius:50%;content:'';height:230px;opacity:.9;position:absolute;right:-80px;top:-70px;width:230px;}
.visual-content,.visual-footer{position:relative;z-index:1}
.brand{display:block;text-decoration:none;width:min(280px, 100%);}
.login-visual .brand-logo{display:block;height:auto;max-width:100%;object-fit:contain;width:100%;}
.visual-content h1{font-family:'Space Grotesk',sans-serif;font-size:2.3rem;line-height:1.08;margin:86px 0 15px;max-width:260px;}
.visual-content p{color:#b9c6cf;font-size:.95rem;line-height:1.7;margin:0;max-width:275px;}
.visual-footer{border-top:1px solid #33424e;color:#8f9eaa;font-size:.76rem;padding-top:18px;}
.login-panel{display:flex;flex-direction:column;justify-content:center;padding:54px 68px;}
.login-panel h2{font-family:'Space Grotesk',sans-serif;font-size:1.8rem;margin:0 0 8px;}
.login-panel .intro{color:#718096;font-size:.9rem;margin:0 0 30px;}
.form-label{color:#52606d;font-size:.78rem;font-weight:700;margin-bottom:8px;}
.input-wrap{position:relative;}
.input-wrap>i{color:#9aa7b2;left:14px;position:absolute;top:12px;z-index:2;}
.form-control{border:1px solid #dce4e9;border-radius:8px;font-size:.9rem;height:46px;padding-left:42px;}
.form-control:focus{border-color:#e56b4f;box-shadow:0 0 0 .2rem rgba(229,107,79,.13);}
.password-toggle{background:none;border:0;color:#8796a3;padding:0 14px;position:absolute;right:0;top:0;height:46px;}
.password-toggle:hover{color:#17212b;}
.login-button{background:#e56b4f;border:0;border-radius:8px;color:#fff;font-weight:700;height:47px;margin-top:8px;transition:background .2s,transform .2s;}
.login-button:hover{background:#cc5c43;color:#fff;transform:translateY(-1px);}
.login-note{color:#9aa7b2;font-size:.73rem;margin:24px 0 0;text-align:center;}
.login-page{background:#f4f6f8;color:#17212b;font-family:'DM Sans',sans-serif;min-height:100vh;margin:0;display:flex;align-items:center;justify-content:center;padding:24px;}
.standalone-login{background:#fff;border:1px solid #e4e9ed;border-radius:18px;box-shadow:0 24px 70px rgba(23,33,43,.13);max-width:480px;width:100%;}
.login-brand{margin:0 auto;}
.alert{border:0;border-radius:8px;font-size:.82rem;margin-bottom:20px;}
@media (max-width:700px){.login-shell{grid-template-columns:1fr;max-width:460px;min-height:0}.login-visual{min-height:210px;padding:28px}.brand{width:min(245px, 100%)}.visual-content h1{font-size:1.7rem;margin:38px 0 8px}.visual-content p{font-size:.82rem;line-height:1.5}.visual-footer{display:none}.login-panel{padding:36px 28px 32px}}
</style>
</head>
<body>
<div class="login-shell">
  <section class="login-visual">
    <div class="visual-content">
      <a class="brand" href="<?= BASE_URL ?>/login.php"><img class="brand-logo" src="<?= BASE_URL ?>/assets/dmp-brand-logo.png" alt="DMP AI Digital Institute"></a>
      <h1>Keep every conversation moving.</h1>
      <p>One focused workspace for your leads, admissions, and team momentum.</p>
    </div>
    <div class="visual-footer"><i class="bi bi-shield-check me-2"></i>Secure access for your team</div>
  </section>
  <section class="login-panel">
  <h2>Welcome back</h2>
  <p class="intro">Sign in to continue to your workspace.</p>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <form method="POST" autocomplete="off">
    <?= csrf_field() ?>
    <div class="mb-3">
      <label class="form-label" for="email">Email address</label>
      <div class="input-wrap"><i class="bi bi-envelope"></i><input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" value="<?= e($prefillEmail) ?>" required autofocus autocomplete="username"></div>
    </div>
    <div class="mb-3">
      <label class="form-label" for="password">Password</label>
      <div class="input-wrap"><i class="bi bi-lock"></i><input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password"><button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" title="Show password"><i class="bi bi-eye"></i></button></div>
    </div>
    <button type="submit" class="btn login-button w-100"><i class="bi bi-arrow-right-circle me-2"></i>Sign in</button>
  </form>
  <p class="login-note">Your account access is protected and monitored.</p>
  </section>
</div>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    var passwordInput = document.getElementById('password');
    if (passwordInput) {
      passwordInput.value = '';
      passwordInput.setAttribute('autocomplete', 'new-password');
    }
  });
</script>
<script src="<?= BASE_URL ?>/assets/js/password-toggle.js"></script>
</body>
</html>
