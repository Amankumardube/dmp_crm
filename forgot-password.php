<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $genericMessage = 'If an active account uses that email, a password reset link has been sent.';

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE email = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()')->execute([$user['id']]);
            $token = bin2hex(random_bytes(32));
            $insert = $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))');
            $insert->execute([$user['id'], hash('sha256', $token)]);
            $resetUrl = BASE_URL . '/reset-password.php?token=' . urlencode($token);
            $subject = APP_NAME . ' password reset';
            $body = "Hello {$user['name']},\n\nUse this link to reset your password:\n{$resetUrl}\n\nThis link expires in 1 hour. If you did not request this, you can ignore this email.\n";
            $headers = 'From: ' . APP_NAME . ' <no-reply@localhost>' . "\r\n" . 'Reply-To: no-reply@localhost' . "\r\n" . 'Content-Type: text/plain; charset=UTF-8';
            if (!@mail($user['email'], $subject, $body, $headers)) {
                $error = 'The email could not be sent. Configure PHP mail settings in XAMPP and try again.';
            } else {
                $message = $genericMessage;
            }
        } else {
            $message = $genericMessage;
        }
    } else {
        $error = 'Enter a valid email address.';
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Forgot Password | <?= APP_NAME ?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"><link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet"></head><body class="login-page"><main class="login-panel standalone-login"><a class="brand login-brand" href="<?= BASE_URL ?>/login.php"><img class="brand-logo" src="<?= BASE_URL ?>/assets/dmp-brand-logo.png" alt="DMP AI Digital Institute"></a><h2 class="mt-4">Reset your password</h2><p class="intro">Enter your account email and we will send a secure reset link.</p><?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="POST"><?= csrf_field() ?><div class="mb-3"><label class="form-label" for="email">Email address</label><div class="input-wrap"><i class="bi bi-envelope"></i><input type="email" id="email" name="email" class="form-control" placeholder="you@example.com" required autofocus></div></div><button class="btn login-button w-100" type="submit"><i class="bi bi-envelope me-2"></i>Send reset link</button></form><p class="text-center mt-4 mb-0"><a href="login.php" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Back to login</a></p></main></body></html>
