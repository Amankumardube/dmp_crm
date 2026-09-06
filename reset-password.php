<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenHash = hash('sha256', $token);
$stmt = $pdo->prepare('SELECT pr.id, u.id AS user_id FROM password_resets pr JOIN users u ON u.id = pr.user_id WHERE pr.token_hash = ? AND pr.expires_at > NOW() AND u.status = \'active\' LIMIT 1');
$stmt->execute([$tokenHash]);
$reset = $stmt->fetch();
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if (!$reset) {
        $error = 'This reset link is invalid or has expired.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Your new password must be at least 8 characters.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'The passwords do not match.';
    } else {
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $update->execute([password_hash($newPassword, PASSWORD_DEFAULT), $reset['user_id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$reset['user_id']]);
            $pdo->commit();
            $success = 'Your password has been reset. You can now sign in.';
            $reset = null;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Unable to reset the password. Please request a new link.';
        }
    }
}
echo '<script src="' . BASE_URL . '/assets/js/password-toggle.js" defer></script>';
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Reset Password | <?= APP_NAME ?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"><link href="<?= BASE_URL ?>/assets/css/style.css" rel="stylesheet"></head><body class="login-page"><main class="login-panel standalone-login"><a class="brand login-brand" href="<?= BASE_URL ?>/login.php"><img class="brand-logo" src="<?= BASE_URL ?>/assets/dmp-brand-logo.png" alt="DMP AI Digital Institute"></a><h2 class="mt-4">Choose a new password</h2><p class="intro">Use at least 8 characters for your new password.</p><?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><a href="login.php" class="btn login-button w-100 text-center">Go to login</a><?php elseif ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><a href="forgot-password.php" class="btn btn-outline-secondary w-100">Request a new link</a><?php elseif ($reset): ?><form method="POST"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>"><div class="mb-3"><label class="form-label" for="new_password">New password</label><input class="form-control" id="new_password" type="password" name="new_password" minlength="8" required autofocus></div><div class="mb-3"><label class="form-label" for="confirm_password">Confirm password</label><input class="form-control" id="confirm_password" type="password" name="confirm_password" minlength="8" required></div><button class="btn login-button w-100" type="submit"><i class="bi bi-shield-check me-2"></i>Reset password</button></form><?php else: ?><div class="alert alert-danger">This reset link is invalid or has expired.</div><a href="forgot-password.php" class="btn btn-outline-secondary w-100">Request a new link</a><?php endif; ?></main></body></html>
